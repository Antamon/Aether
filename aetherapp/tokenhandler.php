<?php
declare(strict_types=1);

// Deze oude callback vormde geen volledige OIDC-flow: de applicatie heeft geen
// initiator die sessiegebonden state en nonce aanmaakt. Laat de route daarom
// geen tokens verwerken of een Aether-sessie opbouwen.
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

echo 'Deze aanmeldroute is uitgeschakeld. Meld aan via WordPress.';
exit;
