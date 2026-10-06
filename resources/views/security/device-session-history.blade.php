<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de sesiones</title>
    <style>
        @page { margin: 28px; }
        body { color: #172033; font-family: Arial, sans-serif; font-size: 10px; }
        h1 { color: #0f172a; font-size: 18px; margin: 0 0 4px; }
        p { color: #64748b; margin: 0 0 18px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #0f172a; color: #ffffff; font-size: 9px; padding: 8px 6px; text-align: left; }
        td { border-bottom: 1px solid #dbe3ef; padding: 7px 6px; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
    </style>
</head>
<body>
    <h1>Historial de actividad y sesiones</h1>
    <p>Registro de accesos y movimientos de {{ $name }}</p>
    <table>
        <thead>
            <tr>
                <th>Fecha y hora</th>
                <th>Usuario</th>
                <th>Dispositivo</th>
                <th>Dirección IP</th>
                <th>Ubicación</th>
                <th>Actividad / movimiento</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['user'] }}</td>
                    <td>{{ $row['device'] }}</td>
                    <td>{{ $row['ip'] }}</td>
                    <td>{{ $row['location'] }}</td>
                    <td>{{ $row['activity'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No hay historial registrado.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
