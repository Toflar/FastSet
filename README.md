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

FastSet uses a **sorted fingerprint array** instead of storing the original strings.

### Build time
1. Read a newline-separated dictionary file.
2. Hash each entry using **`xxh128`** (16 bytes, raw).
3. Sort all fingerprints.
4. Write two files:
   - `hashes.bin` – concatenated 16-byte fingerprints
   - `index.bin` – a 2-byte prefix index (65,537 offsets)

### Lookup time
1. Hash the input string with the same algorithm.
2. Use the **first 2 bytes of the hash** to select a small bucket.
3. Binary-search **only inside that bucket**.

Because hash prefixes are uniformly distributed, buckets are tiny.
Real world tests on the fixture file that contains ~140k entries in this repository (`tests/Fixtures/terms_de.txt`):

- Average bucket size `≈2–3` entries
- Worst case (real data): `11` entries
- Worst case comparisons: `log₂(11) = 4`

Of course, the bigger your dictionary, the bigger the individual buckets.

All comparisons are fixed-width (16 bytes), not variable-length UTF-8 strings.

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

// You then ship either "compressed.txt" or "compressed.gz" with your application. Instantiating 
// is then done as follows:
$set = new FastSet(__DIR__ . '/dict');
$set->build(__DIR__ . '/compressed.(txt|gz)'); // Must be a file built using the SetBuilder
```

Calling `build` creates the following files on-the-fly:
```
dict/
├── hashes.bin
└── index.bin
```

> Important:
> Do not ship `hashes.bin` or `index.bin`.
> Only ship the compressed dictionary created by the `SetBuilder`.
---

### 2. Lookup

Once you have initialized/built your `FastSet` calling `build()` so that the required files have been built, you can 
then use it as follows:

```php
$set = new FastSet(__DIR__ . '/dict');

if ($set->has('look-me-up')) {
    // exists
}
```

The `hashes.bin` and `index.bin` files are loaded lazily on first lookup, but you can also call `initialize()` 
explicitly if you want to load them into memory at a specific point in time.

---

## Caveats

- **Not suitable if you need exact, collision-free guarantees**
- **Not suitable for mutable sets**
- Input normalization (case-folding, Unicode normalization) is intentionally left to the caller

---

## License

MIT
