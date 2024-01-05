# Quiche compilation

## Requirements

Requires `cargo`. It's recommended to install it via `rustup` as recommended by https://www.rust-lang.org/tools/install.

```
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
```

## Local compilation

```
git clone https://github.com/cloudflare/quiche
cd quiche
cargo build --release --features ffi
```

## Cross-compiling

It's possible to cross-compile the quiche artifacts, rather than manually compiling it on each target.

Docker or podman must be installed and running.

This requires access to an apple machine for compiling apple targets.

```
cargo install cross
for target in aarch64-apple-darwin x86_64-apple-darwin; do
	rustup target add $target
	cross build --target $target --release --features ffi,boringssl-boring-crate
done
for target in aarch64-unknown-linux-gnu x86_64-unknown-linux-gnu; do
	rustup target add $target
	cross build --target $target --release --features ffi
done
for target in aarch64-unknown-linux-musl x86_64-unknown-linux-musl; do
	rustup target add $target
	RUSTFLAGS='-Ctarget-feature=-crt-static' cross build --target $target --release --features ffi
done
```

This will generate `libquiche.dylib` files into `target/{aarch64,x86_64}-apple-darwin/release` as well as `libquiche.so` into `target/{aarch64,x86_64}-unknown-linux-{gnu,musl}/release`.

```
this_repo=/path/to/amp/quic
for arch in aarch64 x86_64; do
    cp target/$arch-apple-darwin/release/libquiche.dylib $this_repo/src/bindings/libquiche-$arch.dylib
    for libc in gnu musl; do
        cp target/$arch-unknown-linux-$libc/release/libquiche.so $this_repo/src/bindings/libquiche-$arch-$libc.so
    done
done
```

## Licensing

The compiled libquiche-* artifacts are licensed under the BSD 2-clause: [LICENSE.libquiche](LICENSE.libquiche).
