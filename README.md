# certamen-de-bellesa-senati

Aplicación de votación para el certamen de Miss y Mister Ingeniería de Software de SENATI. Cada estudiante ingresa con su cuenta institucional y puede emitir un voto por categoría. El panel de organización gestiona las candidaturas, los álbumes, el cierre de la votación y los resultados.

## Instalación

Copia `htdocs/conexion.ejemplo.php` como `htdocs/conexion.php` en tu copia local y configura los valores privados siguiendo la [guía de instalación](GUIA.md). Publica el contenido de `htdocs/` en InfinityFree.

Para una instalación nueva, importa `certamen.sql` en una base vacía. Para actualizar una instalación existente con el video del álbum y el historial de borrados, usa `actualizar-panel-y-videos.sql`.
