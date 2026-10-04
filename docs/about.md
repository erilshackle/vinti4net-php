# Sobre o projeto

O Vinti4Net PHP SDK facilita a integração de aplicações PHP com os pagamentos
da [Rede Vinti4](https://www.vinti4.cv/)/SISP em Cabo Verde. Oferece uma API para compras, pagamentos de
serviço, recargas, estornos, processamento das respostas e recibos.

## Referência técnica

A implementação segue a especificação técnica de integração disponibilizada
pela SISP, incluindo os parâmetros das operações, a geração e verificação dos
fingerprints e os dados de billing para 3D Secure. Esta documentação explica
como utilizar a biblioteca; os requisitos do serviço e as orientações fornecidas
pela SISP continuam a ser a referência para cada integração.

Entre os documentos de referência fornecidos está o **MOP021, versão 13,
de maio de 2023**, criado pela SISP.

::: info Documentação do SDK
Estas páginas apresentam explicações e exemplos próprios da biblioteca.
Não disponibilizam nem reproduzem a especificação da SISP. Para obter os
documentos técnicos e esclarecer as condições de utilização, contacte a SISP.
:::

::: warning Projeto independente
Este SDK não é oficial, não representa a SISP e não implica certificação ou
aprovação pela entidade. O comerciante precisa das suas próprias credenciais
e deve seguir as condições e orientações fornecidas para o seu estabelecimento.
:::

## Desenvolvimento e contacto

O projeto é desenvolvido por [Eril TS Carvalho (@erilshackle)](https://github.com/erilshackle).
Código, sugestões e problemas podem ser partilhados no
[repositório do projeto](https://github.com/erilshackle/vinti4net-php).
Para contacto direto: [erilandocarvalho@gmail.com](mailto:erilandocarvalho@gmail.com).

Esta documentação descreve até a série **2.4.x** do pacote `erilshk/vinti4net`,
no namespace `Erilshk\Sisp`. Consulte as [notas de versão](release-notes.md)
para conhecer as alterações entre versões.
