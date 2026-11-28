<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instrucciones para pagar por SPEI</title>
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
                            <h1 style="margin:0 0 8px;font-size:22px;">Tus lugares están apartados</h1>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.5;color:#5a6672;">
                                Para terminar tu compra de <strong style="color:#16202b;">{{ $eventName }}</strong> haz una transferencia SPEI desde la app o el portal de tu banco con estos datos:
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border:1px solid #dce2df;border-radius:10px;font-size:14px;">
                                <tr>
                                    <td style="padding:12px 16px;color:#5a6672;">Monto exacto</td>
                                    <td style="padding:12px 16px;text-align:right;font-size:18px;font-weight:bold;">${{ number_format($amount, 2) }} MXN</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;border-top:1px solid #dce2df;color:#5a6672;">CLABE</td>
                                    <td style="padding:12px 16px;border-top:1px solid #dce2df;text-align:right;font-family:'Courier New',monospace;font-size:16px;font-weight:bold;letter-spacing:1px;">{{ $clabe }}</td>
                                </tr>
                                @if ($bank !== '')
                                    <tr>
                                        <td style="padding:12px 16px;border-top:1px solid #dce2df;color:#5a6672;">Banco</td>
                                        <td style="padding:12px 16px;border-top:1px solid #dce2df;text-align:right;">{{ $bank }}</td>
                                    </tr>
                                @endif
                                @if ($beneficiary !== '')
                                    <tr>
                                        <td style="padding:12px 16px;border-top:1px solid #dce2df;color:#5a6672;">Beneficiario</td>
                                        <td style="padding:12px 16px;border-top:1px solid #dce2df;text-align:right;">{{ $beneficiary }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 16px;border-top:1px solid #dce2df;color:#5a6672;">Concepto o referencia</td>
                                    <td style="padding:12px 16px;border-top:1px solid #dce2df;text-align:right;font-family:'Courier New',monospace;font-weight:bold;">{{ $reference }}</td>
                                </tr>
                            </table>
                            @if ($dueDate)
                                <p style="margin:0 0 16px;padding:12px 16px;border-radius:10px;background:#fff7e6;font-size:14px;line-height:1.5;color:#7a4b00;">
                                    Tienes hasta el <strong>{{ $dueDate->copy()->timezone('America/Mexico_City')->locale('es')->translatedFormat('j \d\e F \a \l\a\s H:i') }} h</strong> para transferir. Si no recibimos el pago a tiempo, tus lugares se liberan.
                                </p>
                            @endif
                            <p style="margin:0 0 20px;font-size:14px;line-height:1.5;color:#5a6672;">
                                Transfiere el monto exacto. En cuanto se confirme el pago te enviaremos tus boletos digitales a este correo.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;">
                                <tr>
                                    <td style="border-radius:10px;background:#22c55e;">
                                        <a href="{{ $orderUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;font-size:16px;font-weight:bold;text-decoration:none;">Ver mi compra</a>
                                    </td>
                                </tr>
                            </table>
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
