<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; }
        .qr-box { background-color: #f8fafc; border: 1px solid #bfdbfe; padding: 15px; border-radius: 8px; margin-top: 20px; text-align: center; }
        .footer { margin-top: 30px; font-size: 12px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <p>{{ $bodyText }}</p>

        <div class="qr-box">
            <h3 style="color: #1e40af; margin-top: 0;">Validación y Descarga Segura</h3>
            <p style="font-size: 14px;">Escanee este código QR para validar la autenticidad de su documento en el portal oficial de la UPRIT.</p>

            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($validationUrl) }}" alt="QR Code de Validación">

            <p style="font-size: 12px; margin-bottom: 0;"><strong>Código de Constancia:</strong> {{ $issuedCertificate->certificate_code }}</p>
        </div>

        <div class="footer">
            <p>Este es un correo automático generado por el Sistema de Registro Académico de la Universidad Privada de Trujillo.</p>
            <p>Por favor, no responda a este mensaje.</p>
        </div>
    </div>
</body>
</html>
