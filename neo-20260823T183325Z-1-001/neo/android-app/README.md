# NEO Remoto para Android

Wrapper Android do NEO local. O app abre a instalação hospedada no computador e mantém um botão **IP** sempre visível para trocar o endereço do servidor sem aguardar uma falha de conexão.

## Comportamento

- No primeiro uso, solicita o IP ou a URL completa do computador.
- Em usos seguintes, tocar em **IP** troca apenas o IP e mantém o caminho do NEO.
- Também existe o atalho **Trocar IP** ao manter pressionado o ícone do aplicativo.
- A preferência existente usa o mesmo armazenamento (`connection` / `server`) da versão 1.0.1.

## Gerar o APK

Com o SDK Android instalado, execute `build.ps1`. O APK assinado é copiado para `../downloads/NEO-Remoto.apk` e o SHA-256 é atualizado.

A chave em `signing/neo-demo.jks` é exclusiva deste ambiente de demonstração. Não deve ser usada para publicação em loja ou em produção.

O APK 1.0.1 original foi recebido sem sua chave privada. Por isso, a passagem daquela versão para a 1.1.0 exige desinstalar o app antigo uma vez. As versões compiladas daqui em diante reutilizam a chave local acima e podem ser instaladas como atualização.
