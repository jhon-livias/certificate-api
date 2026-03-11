<!DOCTYPE html>
<html>
<body>
    <p>Estimado(a) estudiante <strong>{{ $certificate->student->full_name }}</strong>,</p>
    <p>Adjunto encontrará su constancia generada.</p>

    <div style="border: 1px solid #bfdbfe; padding: 20px; border-radius: 8px;">
        <img src="{{ $message->embed($qrPath) }}" alt="QR" width="100" style="float: left; margin-right: 15px;">
        <div>
            <h4 style="color: #1e40af; margin-top: 0;">Validación y Descarga Segura</h4>
            <p style="font-size: 13px;">Escanee este código QR incrustado para validar su documento.</p>
            <ul style="font-size: 13px;">
                <li>Código de Estudiante: <strong>{{ $certificate->student->student_code }}</strong></li>
                <li>Código Único: <strong>{{ $certificate->tracking_code }}</strong></li>
            </ul>
        </div>
    </div>
</body>
</html>
