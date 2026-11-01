{{ $appName }} — Sua senha temporária

Olá{{ $name !== '' ? ', '.$name : '' }}. Recebemos um pedido de "Esqueceu a senha?" para a sua conta no {{ $appName }}.

Senha temporária: {{ $temporaryPassword }}
Válida por {{ $expiresInMinutes }} minutos.

Entre em {{ $loginUrl }} com esta senha. Em seguida, você vai criar uma nova senha.

Não pediu? Por segurança, sua senha anterior deixou de valer. Entre com esta senha temporária e crie uma nova.
