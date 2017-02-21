<?php
$encData = 'n1n10ERgtWpaijEvs/sDpvxQ8l85JSUTedDQXd0FZ/zn+tlaB5bRSdgpC37MJpXtgPAowRTTlOkD5+rDREJpYQ==:1000:LdvvHQFA8UAN+B2rCM+PbqIxrMbWPeep5NwhqmLiLHgR4FxedulHp2Mwpl3kDlkqzONIHgVPWAnx313nNqbxof8abK04EWUXfvaoDBBab1Dc+0zUHG5EwZQh1z0taqUFH1gG1IGJGUhb6NiJt4erYX4brPHKU455Qqb8CTIkKSX4VJJovbt0lAhxgmwLUoCl4NmYMJgNQgjYYm4sfPWHE/WCV2mgGMieaQXboh1mF7vj//JgZUx3ViopGha445ULFBZHZN1r9m4D2Xv/3baCyA==';
$cookieKey = '1qoEAOpl5KU/4Uq3CJQXdBsdYgIcltf6oGL1BFqKUJNx8FTj4Wrk/ad+N0s7sFDV';

$ivlen = 16;
$keylen = 32;
$digest = 'sha256';
list($salt, $iterations, $encCookie) = explode(':', $encData);

$iterations = intval($iterations);
$salt = base64_decode($salt);
$ciphertext = base64_decode($encCookie);
$derivation = hash_pbkdf2($digest, $cookieKey, $salt, $iterations, $ivlen + $keylen, true);
$key = substr($derivation, 0, $keylen);
$iv = substr($derivation, $keylen);
$cookie = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $ciphertext, MCRYPT_MODE_CBC, $iv);

echo $cookie . "\n";