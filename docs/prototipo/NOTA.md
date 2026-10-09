# Prototipo original (referencia funcional)

Copia del prototipo `plataforma-catalogos` recibido el 17 sep 2026. Se conserva tal cual como
**especificación de comportamiento**: el `README.md` de esta carpeta describe el modelo de negocio
que la aplicación Laravel reproduce en el servidor.

No se versionan:

- `dist/` (HTML autocontenido de 8 MB, se regenera con `python3 build.py`).
- Las 16 páginas JPG de las octavillas de Jumbo y Metro (arte final real de Cencosud). Para
  correr el prototipo o los seeders, colócalas en `storage/app/seed/paginas/` (ignorado por git)
  y, para el prototipo, enlázalas en `docs/prototipo/assets/`:

```bash
ln -s ../../../storage/app/seed/paginas/*.jpg docs/prototipo/assets/
```

Para abrirlo: `cd docs/prototipo && python3 -m http.server 8777`.
