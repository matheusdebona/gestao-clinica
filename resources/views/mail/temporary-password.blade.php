<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Sua senha temporária — {{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background:#eef0f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Inter,Roboto,Helvetica,Arial,sans-serif;color:#1d1b33;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">Use esta senha temporária para entrar e criar uma nova senha. Ela vale por {{ $expiresInMinutes }} minutos.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f7;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 8px 30px rgba(89,86,166,0.12);">
                    <tr>
                        <td align="center" style="background:#5956a6;padding:28px 24px;">
                            <img src="{{ $logoUrl }}" width="56" height="56" alt="{{ $appName }}" style="display:block;border:0;border-radius:14px;">
                            <p style="margin:12px 0 0;font-size:13px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;color:#ffffff;">{{ $appName }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px 8px;">
                            <h1 style="margin:0;font-size:24px;line-height:1.25;font-weight:600;letter-spacing:-0.02em;color:#1d1b33;">Sua senha temporária</h1>
                            <p style="margin:14px 0 0;font-size:15px;line-height:1.6;color:#4b4a63;">
                                Olá{{ $name !== '' ? ', '.e($name) : '' }}. Recebemos um pedido de “Esqueceu a senha?” para a sua conta no {{ $appName }}.
                            </p>
                            <p style="margin:12px 0 0;font-size:15px;line-height:1.6;color:#4b4a63;">
                                Entre com a senha abaixo. Em seguida, você vai criar uma nova senha.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 28px;">
                            <div style="display:inline-block;padding:16px 22px;border-radius:14px;background:#eef0f7;border:1px solid #d9dbee;font-family:'SFMono-Regular',Menlo,Consolas,'Liberation Mono',monospace;font-size:24px;font-weight:700;letter-spacing:0.08em;color:#4a4791;">{{ $temporaryPassword }}</div>
                            <p style="margin:10px 0 0;font-size:13px;color:#6b6a80;">Válida por {{ $expiresInMinutes }} minutos.</p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:4px 28px 28px;">
                            <a href="{{ $loginUrl }}" style="display:inline-block;padding:14px 28px;border-radius:12px;background:#5956a6;color:#ffffff;font-size:16px;font-weight:600;text-decoration:none;">Entrar no {{ $appName }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 28px;">
                            <p style="margin:0;padding-top:20px;border-top:1px solid #ececf4;font-size:13px;line-height:1.6;color:#6b6a80;">
                                Não pediu? Ignore este e-mail. Sua senha atual continua valendo e ninguém consegue entrar sem esta senha temporária.
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:16px 0 0;font-size:12px;color:#8a89a0;">{{ $appName }} · Gestão da clínica, com calma.</p>
            </td>
        </tr>
    </table>
</body>
</html>
