export async function getDeviceModel() {
  if (typeof navigator === 'undefined') return null;

  const hints = navigator.userAgentData;
  if (typeof hints?.getHighEntropyValues === 'function') {
    try {
      const values = await hints.getHighEntropyValues(['model']);
      const model = String(values.model ?? '').trim();
      if (model) return model.slice(0, 120);
    } catch {
      // Some browsers do not expose high-entropy client hints.
    }
  }

  const userAgent = navigator.userAgent ?? '';
  if (/iPhone/i.test(userAgent)) return 'iPhone (modelo no expuesto)';
  if (/iPad/i.test(userAgent)) return 'iPad (modelo no expuesto)';

  return null;
}
