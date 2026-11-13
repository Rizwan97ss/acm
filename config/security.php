<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blind Index Key
    |--------------------------------------------------------------------------
    |
    | Encrypted columns cannot be searched. For fields that need exact-match
    | lookups (phone, national ID) we store a keyed HMAC "blind index" next to
    | the ciphertext. This key MUST differ from APP_KEY, and rotating it
    | requires recomputing every stored index.
    | When empty, a key is derived from APP_KEY with a domain separator.
    |
    */

    'blind_index_key' => env('BLIND_INDEX_KEY'),

];
