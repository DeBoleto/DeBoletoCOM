<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gracias por tu compra</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f5;font-family:Arial,Helvetica,sans-serif;color:#16202b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f5;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;border:1px solid #dce2df;">
                    <tr>
                        <td style="background:#16202b;border-radius:14px 14px 0 0;padding:20px 28px;color:#4ecba0;font-size:18px;font-weight:bold;">
                            <img src="{{ asset('logo_blanco.png') }}" width="150" height="29" alt="DeBoleto" style="display:block;border:0;outline:none;text-decoration:none;height:29px;width:150px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 8px;font-size:22px;">¡Gracias por tu compra!</h1>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.5;color:#5a6672;">
                                Tu compra para <strong style="color:#16202b;">{{ $eventName }}</strong> (referencia {{ $reference }}) fue realizada con éxito. Para obtener tus boletos digitales da clic en el siguiente botón:
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td style="border-radius:10px;background:#22c55e;">
                                        <a href="{{ $ticketsUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;font-size:16px;font-weight:bold;text-decoration:none;">Boletos digitales</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 8px;font-size:14px;font-weight:bold;">Importante:</p>
                            <ul style="margin:0 0 16px;padding-left:20px;font-size:14px;line-height:1.6;color:#5a6672;">
                                <li>Para ver tus boletos abre el enlace en el navegador de tu celular.</li>
                                <li>Tus boletos tienen un QR dinámico que cambia cada 5 minutos: no los imprimas ni intentes entrar con una captura de pantalla.</li>
                                <li>Por tu seguridad, no compartas tus boletos con personas desconocidas ni en redes sociales.</li>
                            </ul>
                            <p style="margin:0;font-size:12px;line-height:1.5;color:#8a949d;">
                                Si el botón no funciona, copia este enlace en tu navegador:<br>
                                <a href="{{ $ticketsUrl }}" style="color:#22c55e;word-break:break-all;">{{ $ticketsUrl }}</a>
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
