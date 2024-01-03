<?php

require __DIR__ . "/../../vendor/autoload.php";

if (count($argv) < 2 || !in_array($argv[1], ["dev", "release"])) {
    die("Invocation: {$argv[0]} dev|release\n");
}

$dev = $argv[1] == "dev";

// libraries generated via: cargo build --release --features ffi
$path = __DIR__ . "/libquiche." . (PHP_OS_FAMILY === "Darwin" ? "dylib" : "so");

$quiche = (new \FFIMe\FFIMe($path, [
    __DIR__,
]))->include("quiche-bindings.h");

$gen = function ($instrument) use ($quiche, $path) {
    $code = "<?php\n" . $quiche->compile('Amp\Quic\Bindings\Quiche', $instrument);
    $code = preg_replace('(struct (?:__sockaddr_header|sockaddr(?:|_in6?|_storage)) {\s++\K(?:.*\s+(.+?)_len;\s*|(?=\S+\s([^_]+))))', "' . (PHP_OS_FAMILY === 'Darwin' ? 'uint8_t $1_len;' : '') . '\n  ", $code);
    $code = str_replace("'$path'", "__DIR__ . '/libquiche.' . (PHP_OS_FAMILY === 'Darwin' ? 'dylib' : 'so')", $code);
    file_put_contents(__DIR__ . "/quiche.php", $code);
};

if (!$dev) {
    $gen(true);

    (new PHPUnit\TextUI\Command)->run([$argv[0], "test"], false);
    \Amp\Quic\Bindings\QuicheFFI::$__visitedFunctions["quiche_enable_debug_logging"] = true;
}

$gen(false);
