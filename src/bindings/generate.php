<?php declare(strict_types=1);

require __DIR__ . "/../../vendor/autoload.php";

if (count($argv) < 2 || !in_array($argv[1], ["dev", "release"])) {
    die("Invocation: {$argv[0]} dev|release\n");
}

$dev = $argv[1] == "dev";

$evalPath = <<<'EVAL'
if (!defined('AMP_QUIC_LIBQUICHE_PATH')) {
    $uname_map = ['aarch64' => 'aarch64', 'arm64' => 'aarch64', 'amd64' => 'x86_64', 'x86_64' => 'x86_64'];
    if (PHP_OS_FAMILY === 'Darwin') {
        $suffix = '.dylib';
    } elseif (glob('/lib/ld-musl-*')) {
        $suffix = '-musl.so';
    } else {
        $suffix = '-gnu.so';
    }
    define('AMP_QUIC_LIBQUICHE_PATH', __DIR__ . "/libquiche-{$uname_map[php_uname('m')]}$suffix");
}
EVAL;
eval($evalPath);

$quiche = (new \FFIMe\FFIMe(AMP_QUIC_LIBQUICHE_PATH, [
    __DIR__,
]))->include("quiche-bindings.h");

$gen = function ($instrument) use ($quiche, $evalPath) {
    [$namespace, $code] = explode("\n", $quiche->compile('Amp\Quic\Bindings\Quiche', $instrument), 2);
    $code = "<?php\n\n$namespace\n\n$evalPath\n\n$code";
    $code = preg_replace('(struct (?:__sockaddr_header|sockaddr(?:|_in6?|_storage)) {\s++\K(?:.*\s+(.+?)_len;\s*|(?=\S+\s([^_]+))))', "' . (PHP_OS_FAMILY === 'Darwin' ? 'uint8_t $1_len;' : '') . '\n  ", $code);
    $code = str_replace("'" . AMP_QUIC_LIBQUICHE_PATH . "'", "AMP_QUIC_LIBQUICHE_PATH", $code);
    file_put_contents(__DIR__ . "/quiche.php", $code);
};

if (!$dev) {
    $gen(true);

    (new PHPUnit\TextUI\Command)->run([$argv[0], "test"], false);
    \Amp\Quic\Bindings\QuicheFFI::$__visitedFunctions["quiche_enable_debug_logging"] = true;
}

$gen(false);
