# FastSet

**FastSet** is a tiny, read-only, memory-efficient membership set for PHP.

It answers exactly one question:

> Does this string exist in my set?

…and it does so **very fast** and with **very little memory**, even for large sets.

---

## Why FastSet exists

PHP’s native arrays are extremely fast for lookups, but they are also **very memory-hungry**.  
Storing large dictionaries (100k+ strings) quickly becomes impractical, especially when you need multiple sets at once.

FastSet is designed for cases where:

- The dictionary is **static** (no inserts, deletes, or updates).
- You only need **`has(string $entry): bool`**.
- **Memory usage matters**.
- Lookup speed must be **predictable and fast**.

Typical use cases:
- Linguistic dictionaries (e.g. word decomposition)
- Large allow/deny lists
- Static vocabularies
- Read-only validation sets

---

## Design overview

FastSet uses a **sorted fingerprint blob** instead of storing the original strings.

### Build time
1. Read to original dictionary built using the `SetBuilder`
2. Hash each entry using the configured hash
3. Sort all fingerprints.
4. Write two files:
   - `hashes_<hash_algorithm>.bin` – concatenated fingerprints (without the 2-byte prefixes for maximum compression)
   - `index_<hash_algorithm>.bin` – a 2-byte prefix index (65,537 offsets)

### Lookup time
1. Hash the input string with the same algorithm.
2. Use the **first 2 bytes of the hash** to select a small bucket.
3. Binary-search **only inside that bucket**.

Because hash prefixes are uniformly distributed, buckets are tiny.
Real world tests on the fixture file that contains `~134,000` entries in this repository (`tests/Fixtures/terms_de.txt`)
using the `xxh3` hash algorithm:

- Used buckets: `57,112 (≈ 87.2%)`
- Average non-empty bucket size: `2.36` entries
- Worst case bucket size (biggest bucket): `11` entries
- Worst case comparisons: `log₂(11) = 4`

Of course, the bigger your dictionary, the bigger the individual buckets.

All comparisons are fixed-width, not variable-length UTF-8 strings.

---

## Properties

- ✅ **Very low memory usage**
- ✅ **Predictable lookup cost**
- ✅ **No PHP hash tables**
- ✅ **No database**
- ✅ **No dependencies**
- ⚠️ **Read-only**
- ⚠️ **Hash collisions are theoretically possible**  
  (acceptable for many linguistic and heuristic use cases)

---

## Installation

```
composer require toflar/fast-set
```

---

## Usage

### 1. Build the set (one-time)

Hashes are an excellent tool for evenly distributing entries, which is exactly what makes lookups in `FastSet` 
extremely fast. However, hashes are not well suited for distribution:

* They are often larger than the original terms (especially for short words). 
* They are effectively random, which means gzip compression performs very poorly.

Shipping prebuilt hash files would therefore often mean shipping more data than the original dictionary.

The solution: The `SetBuilder`

This library ships with a `SetBuilder` that is designed specifically for distribution size efficiency.
Instead of shipping hashes, you ship a compressed dictionary that:

* exploits shared prefixes between terms
* avoids repeating identical prefixes 
* compresses extremely well with gzip

The hash-based data structures are then generated locally at build time.

```php
$myOriginalSet = __DIR__ . '/dictionary.txt'; // one entry per line

// Encode/Compress with the prefix algorithm:
SetBuilder::buildSet($myOriginalSet, './compressed.txt');

// Encode/Compress with the prefix algorithm and gzip on top (the .gz suffix determines that):
SetBuilder::buildSet($myOriginalSet, './compressed.gz');

// If your dictionary is not a line-feed separated file, but you have an array, you can also build it from an
// array directly:
SetBuilder::buildFromArray($myArray, './compressed.gz');

// You can use either xxh3 (default) or xxh128
// xxh128 will have a smaller probability for false-positives but require more memory
$hashAlgorithm = 'xxh3';

// You then ship either "compressed.txt" or "compressed.gz" with your application. Instantiating 
// is then done as follows:
$set = new FastSet(__DIR__ . '/dict', $hashAlgorithm);
$set->build(__DIR__ . '/compressed.(txt|gz)'); // Must be a file built using the SetBuilder
```

Calling `build` creates the following files on-the-fly:
```
dict/
├── hashes_<hash_algorithm>.bin
└── index_<hash_algorithm>.bin
```

> Important:
> Do not ship `hashes_<hash_algorithm>.bin` or `index_<hash_algorithm>.bin`.
> Only ship the compressed dictionary created by the `SetBuilder`.
---

#### How to choose the right hashing algorithm

This library only supports the use of xxHash. Why? Because it's [extremely fast](https://xxhash.com/).
It does, however, not make sense to allow all versions of xxHash. Hence, only `xxh3` (64bit) and `xxh128` 
(128bit)
are supported. 

`xxh3` will almost always be the right choice for you. That's why it's the default. But decide for yourself:

Collision probability by hash size for `100_000` terms:

| Hash   | Bits | Probability of ≥1 collision | Interpretation    |
|--------| ---- | --------------------------- |-------------------|
| xxh32  | 32   | ~0.31 (31%)                 | ❌ Unacceptable   |
| xxh3   | 64   | ~2.7 × 10⁻¹⁰                | ~1 in 3.7 billion |
| xxh128 | 128  | ~1.5 × 10⁻²⁹                | Effectively zero  |

Collision probability by hash size for `500_000` terms:

| Hash   | Bits | Probability of ≥1 collision | Interpretation           |
|--------| ---- | --------------------------- |--------------------------|
| xxh32  | 32   | ~1.0                        | ❌ Guaranteed collisions |
| xxh3   | 64   | ~6.8 × 10⁻⁹                 | ~1 in 147 million        |
| xxh128 | 128  | ~3.7 × 10⁻²⁸                | Effectively zero         |


Which algorithm to choose depends on the risk of collisions you want to take but as long as you are < 1 million 
terms, 64-bit is astronomically safe. Maybe this is a good rule of thumb:

> Use `xxh3` by default.
> Switch to `xxh128` if your set contains millions of terms, or you need essentially zero collision risk.

### 2. Lookup

Once you have initialized/built your `FastSet` calling `build()` so that the required files have been built, you can 
then use it as follows:

```php
$set = new FastSet(__DIR__ . '/dict');

if ($set->has('look-me-up')) {
    // exists
}
```

The `hashes_<hash_algorithm>.bin` and `index_<hash_algorithm>.bin` files are loaded lazily on first lookup, but you can also call `initialize()` 
explicitly if you want to load them into memory at a specific point in time.

---

## Caveats

- **Not suitable if you need exact, collision-free guarantees**
- **Not suitable for mutable sets**
- Input normalization (case-folding, Unicode normalization) is intentionally left to the caller

---

## License

MIT
