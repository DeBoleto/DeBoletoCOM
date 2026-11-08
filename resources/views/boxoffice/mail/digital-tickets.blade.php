<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tus boletos</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f5;font-family:Arial,Helvetica,sans-serif;color:#16202b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f5;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;border:1px solid #dce2df;">
                    <tr>
                        <td style="background:#16202b;border-radius:14px 14px 0 0;padding:20px 28px;color:#4ecba0;font-size:18px;font-weight:bold;">DeBoleto</td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 8px;font-size:22px;">Gracias por tu compra</h1>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.5;color:#5a6672;">
                                Adjuntamos tus boletos para <strong style="color:#16202b;">{{ $eventName }}</strong>
                                (folio {{ $purchaseId }}).
                            </p>
                            <p style="margin:0;font-size:14px;line-height:1.5;color:#5a6672;">
                                Presenta el código QR de cada boleto en el acceso, desde tu teléfono o impreso. Cada código vale para una sola entrada.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;border-top:1px dashed #dce2df;font-size:12px;color:#8a949d;">
                            ¿Dudas? Escríbenos a contacto@deboleto.com
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
