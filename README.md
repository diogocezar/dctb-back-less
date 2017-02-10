# Back Less

Este é um exemplo de api que permite o acesso de sites remotos para a realização de operações em php, neste exemplo o envio de emails com a plataforma SendGrid.

Motivação: Poder enviar um email diretamente do JavaScript.

Para controlar o acesso um token deve ser gerado para garantir que o acesso será feito apenas dos hosts permitidos no sistema.

A lógica do token é simples e pode ser alterada, neste exemplo, o domínio invertido concatenado de uma key é codificado para MD5.

Também está implementado um JavaScript para automatização do processo de envio de informações, que capta automaticamente os dados do formulário que agregar 2 chaves:

```
<form data-backless-form="true">
<button data-backless-send="true">Enviar</button>
```

Com isso, para enviar um email, basta incluir o javascript na página html, configurar o botão de envio e o formulário, e fazer o acesso a sua api hospedada em seu servidor.