# FuzzCache
FuzzCache is a software-based data cache mechanism that complements and optimizes dynamic web application fuzzing. It is based on a key observation that data fetch is often repeated, redundant, yet expensive during web application fuzzing. FuzzCache thus stores the data into software-based in-memory caches, eliminating the need for repeated and expensive operations. More technical details can be found in the paper.

```tex
@inproceedings{fuzzcache,
    title       = {FuzzCache: Optimizing Web Application Fuzzing Through Software-Based Data Cache},
    author      = {Li, Penghui and Zhang, Mingxue},
    booktitle   = {Proceedings of 31st ACM Conference on Computer and Communications Security (CCS)},
    month       = oct,
    year        = 2024
}
```
