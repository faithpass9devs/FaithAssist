import 'dotenv/config';
import express from 'express';
import makeWASocket, {
  Browsers,
  DisconnectReason,
  fetchLatestBaileysVersion,
  useMultiFileAuthState,
} from '@whiskeysockets/baileys';
import QRCode from 'qrcode-terminal';
import fs from 'node:fs/promises';
import path from 'node:path';

const app = express();
const port = Number(process.env.BAILEYS_PORT || 3001);
const token = process.env.BAILEYS_INTERNAL_TOKEN || '';

if (!token) {
  console.error('BAILEYS_INTERNAL_TOKEN es requerido para iniciar el servidor Baileys.');
  process.exit(1);
}

const authDir = process.env.BAILEYS_AUTH_DIR || 'storage/app/baileys/auth';
const lockFile = process.env.BAILEYS_LOCK_FILE || path.join(process.cwd(), 'storage/app/baileys/.baileys.lock');
const phoneNumber = String(process.env.BAILEYS_PHONE_NUMBER || '').replace(/\D/g, '');
let socket = null;
let connected = false;
let reconnectDelay = 1000;
let connecting = false;
let pairingIssued = false;
const pendingAcks = new Map();
const recentAcks = new Map();

async function acquireLock() {
  const lockDir = path.dirname(lockFile);
  await fs.mkdir(lockDir, { recursive: true });

  try {
    const existingPid = (await fs.readFile(lockFile, 'utf8')).trim();
    if (existingPid) {
      const pid = Number.parseInt(existingPid, 10);
      if (Number.isInteger(pid)) {
        try {
          process.kill(pid, 0);
          console.error(`Ya existe una instancia de Baileys activa con PID ${pid}. Saliendo para evitar conflicto de sesión.`);
          process.exit(0);
        } catch {
          await fs.rm(lockFile, { force: true });
        }
      }
    }
  } catch {
    // Sin bloqueo previo; se continúa normalmente.
  }

  await fs.writeFile(lockFile, String(process.pid), 'utf8');
}

async function releaseLock() {
  try {
    await fs.rm(lockFile, { force: true });
  } catch {
    // Ignorar fallos al liberar el lock.
  }
}

process.on('exit', () => {
  void releaseLock();
});
process.on('SIGINT', () => {
  void releaseLock().finally(() => process.exit(0));
});
process.on('SIGTERM', () => {
  void releaseLock().finally(() => process.exit(0));
});

async function resetSession(reason) {
  console.error(`Se detectó un conflicto de sesión de WhatsApp (${reason}).`);
  console.error('Elimina la sesión actual de Baileys y vuelve a vincular el número desde WhatsApp > Dispositivos vinculados.');

  try {
    await fs.rm(authDir, { recursive: true, force: true });
    console.error('Sesión de Baileys eliminada correctamente. Reinicia el servidor para crear una nueva vinculación.');
  } catch (error) {
    console.error('No se pudo limpiar la sesión de Baileys automáticamente:', error.message);
  }

  process.exit(1);
}

await acquireLock();

app.use(express.json({ limit: '30mb' }));
app.use((request, response, next) => {
  if (request.get('authorization') !== `Bearer ${token}`) {
    return response.status(401).json({ message: 'No autorizado.' });
  }
  next();
});

async function connect() {
  if (connecting) return;
  connecting = true;

  try {
    const { state, saveCreds } = await useMultiFileAuthState(authDir);
    let version;

    try {
      ({ version } = await fetchLatestBaileysVersion());
    } catch (error) {
      console.error('No se pudo consultar la version mas reciente de WhatsApp:', error.message);
    }

    socket = makeWASocket({
      auth: state,
      ...(version ? { version } : {}),
      browser: Browsers.macOS('Desktop'),
      markOnlineOnConnect: false,
      printQRInTerminal: false,
      connectTimeoutMs: 60000,
      qrTimeout: 60000,
    });

    socket.ev.on('creds.update', saveCreds);
    socket.ev.on('messages.update', (updates) => {
      for (const { key, update } of updates) {
        const messageId = key?.id;
        const pending = pendingAcks.get(messageId);

        if (update?.status === 0) {
          if (pending) {
            pending.reject(new Error('WhatsApp reportó un error al entregar el mensaje.'));
            pendingAcks.delete(messageId);
          }
        } else if (update?.status >= 1) {
          if (pending) {
            pending.resolve(update.status);
            pendingAcks.delete(messageId);
          } else {
            recentAcks.set(messageId, update.status);
          }
        }
      }
    });

    if (!state.creds.registered && phoneNumber && !pairingIssued) {
      setTimeout(() => {
        if (socket?.user || pairingIssued) return;

        pairingIssued = true;
        socket.requestPairingCode(phoneNumber)
          .then((code) => {
            console.log(`\nCodigo de vinculacion de WhatsApp: ${code}`);
            console.log('Introducelo ahora en WhatsApp > Dispositivos vinculados > Vincular con numero de telefono.\n');
          })
          .catch((error) => {
            pairingIssued = false;
            console.error('No se pudo generar el codigo de vinculacion:', error.message);
          });
      }, 2500);
    }

    socket.ev.on('connection.update', ({ connection, lastDisconnect, qr }) => {
      if (qr) {
        console.log('\nEscanea este QR desde WhatsApp > Dispositivos vinculados:\n');
        QRCode.generate(qr, { small: true });
      }

      connected = connection === 'open';
      connecting = connection === 'connecting';

      if (connected) {
        reconnectDelay = 1000;
        console.log('WhatsApp Baileys conectado.');
      }

      if (connection === 'close') {
        connecting = false;
        const statusCode = lastDisconnect?.error?.output?.statusCode;
        const errorMessage = lastDisconnect?.error?.message || 'sin detalle';

        console.error(`Baileys cierre: codigo=${statusCode ?? 'desconocido'}, detalle=${errorMessage}`);

        if (statusCode === DisconnectReason.loggedOut || statusCode === 440) {
          console.error('La sesión de WhatsApp fue reemplazada o cerrada desde otro equipo/session.');
          console.error('Se requiere volver a vincular el número de WhatsApp.');
          void resetSession(statusCode ?? 'conflict');
          return;
        }

        console.error(`Baileys se desconectó. Reintentando en ${reconnectDelay / 1000}s.`);
        setTimeout(connect, reconnectDelay);
        reconnectDelay = Math.min(reconnectDelay * 2, 30000);
      }
    });
  } catch (error) {
    connecting = false;
    console.error('No se pudo iniciar Baileys:', error.message);
    setTimeout(connect, reconnectDelay);
    reconnectDelay = Math.min(reconnectDelay * 2, 30000);
  }
}

app.get('/status', (request, response) => response.json({ connected }));

app.post('/send', async (request, response) => {
  if (!connected || ! socket) return response.status(503).json({ message: 'WhatsApp no está conectado.' });
  const { to, text, document_path: documentPath, filename } = request.body;
  const phone = String(to).replace(/\D/g, '');

  try {
    const contacts = await socket.onWhatsApp(phone);
    const contact = contacts?.find((item) => item.exists && item.jid);
    const jid = contact?.jid || `${phone}@s.whatsapp.net`;

    if (!contact) {
      console.error(`WhatsApp no encontro al destinatario ${phone}.`);
      return response.status(422).json({
        message: 'El número destinatario no tiene una cuenta de WhatsApp disponible.',
      });
    }

    let result;
    if (documentPath) {
      const allowedRoot = path.resolve(process.cwd(), 'storage/app');
      const resolvedPath = path.resolve(String(documentPath));

      if (!resolvedPath.startsWith(`${allowedRoot}${path.sep}`)) {
        return response.status(422).json({ message: 'Ruta de documento no permitida.' });
      }

      result = await socket.sendMessage(jid, {
        document: await fs.readFile(resolvedPath),
        mimetype: 'application/pdf',
        fileName: filename || 'gafete.pdf',
        caption: text || undefined,
      });
    } else {
      result = await socket.sendMessage(jid, { text });
    }
    const messageId = result?.key?.id || null;
    const ackStatus = messageId ? await waitForAck(messageId) : null;
    console.log(`Mensaje enviado a ${jid}: ${messageId || 'sin id'} (ack=${ackStatus ?? 'none'})`);
    response.json({
      message_id: messageId,
      jid,
      ack_status: ackStatus,
    });
  } catch (error) {
    console.error(`Error enviando a ${phone}:`, error.message || error);
    response.status(502).json({ message: error.message || 'No se pudo enviar el mensaje.' });
  }
});

function waitForAck(messageId) {
  return new Promise((resolve, reject) => {
    if (recentAcks.has(messageId)) {
      const status = recentAcks.get(messageId);
      recentAcks.delete(messageId);
      resolve(status);
      return;
    }

    const timeout = setTimeout(() => {
      pendingAcks.delete(messageId);
      reject(new Error('WhatsApp no confirmó la entrega dentro del tiempo esperado.'));
    }, 15000);

    pendingAcks.set(messageId, {
      resolve: (status) => {
        clearTimeout(timeout);
        resolve(status);
      },
      reject: (error) => {
        clearTimeout(timeout);
        reject(error);
      },
    });
  });
}

await connect();
app.listen(port, () => console.log(`Baileys server escuchando en ${port}`));
