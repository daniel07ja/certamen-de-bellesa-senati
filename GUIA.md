# Certamen SENATI · Guía de instalación

Aplicación para PHP 8.0 o superior con PDO MySQL, cURL, Fileinfo y sesiones habilitadas. Usa MySQL o MariaDB con tablas InnoDB. Los estilos de Tailwind están precompilados y se sirven desde el mismo sitio.

## Estructura del proyecto

```text
certamen-senati/
├── GUIA.md
├── LICENCIA-TAILWIND.txt
├── certamen.sql
└── htdocs/
    ├── .htaccess
    ├── index.php           Galería y acceso estudiantil
    ├── login.php           Inicio y cierre de sesiones
    ├── senati.php          Inicio de sesión con Microsoft 365 y retorno al certamen
    ├── votar.php           Registro transaccional del voto
    ├── admin.php           Administración y gestión de participantes
    ├── resultados.php     Conteo JSON privado
    ├── app.php            Sesiones, consultas y componentes compartidos
    ├── conexion.php       Credenciales MySQL y configuración
    ├── assets/
    │   ├── app.css
    │   ├── app.js
    │   └── tailwind.css
    ├── img/
    │   └── .htaccess      Coloca aquí logo-senati.png
    └── uploads/
        └── .htaccess      PHP añade aquí las fotos
```

## Instalación en InfinityFree

1. Extrae el ZIP en tu equipo. En InfinityFree, abre la cuenta de hosting y entra a **MySQL Databases**. Crea una base, por ejemplo `certamen`. Copia el nombre completo, el usuario, el hostname MySQL y la contraseña de la cuenta de hosting que figura en **MySQL Details**. InfinityFree asigna un hostname propio a MySQL. [Solución de errores MySQL de InfinityFree](https://forum.infinityfree.com/t/common-mysql-connection-errors/49338)
2. Abre **phpMyAdmin**, selecciona la base recién creada, entra a **Importar** y carga `certamen.sql`. Comprueba que aparezcan `administradores`, `candidatos`, `votantes`, `fotos_candidato` y `configuracion`. Haz la importación inicial en una base vacía. Para respaldos posteriores, usa **Exportar** y guarda también una copia privada de `uploads/`.
3. Abre `htdocs/conexion.php` en un editor de texto y sustituye `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` por los valores del paso 1. Si un valor contiene una barra inversa o comilla simple, escápala como `\\` o `\'` dentro de la cadena PHP.
4. Genera 32 caracteres hexadecimales aleatorios y pégalos en `CLAVE_INSTALACION`. Con PHP local puedes ejecutar `php -r "echo bin2hex(random_bytes(16));"`. Guarda esa clave en privado. Mantén `EXIGIR_HTTPS = true` en producción y comprueba que tu dominio abre por HTTPS. Si necesita un certificado, usa **Free SSL Certificates** en el área de cliente y sigue las indicaciones para tu dominio. [Guía oficial de HTTPS](https://forum.infinityfree.com/t/how-to-get-free-ssl-https-on-infinityfree/49323)
5. Abre **File Manager** desde la cuenta de hosting. Entra a la carpeta `htdocs` correspondiente al dominio. Carga el **contenido** de la carpeta local `htdocs` en esa carpeta remota, manteniendo la jerarquía. Si el gestor admite extraer ZIP, comprime localmente solo el contenido de `htdocs`, extráelo dentro de la carpeta remota y elimina del servidor ese ZIP cuando termine. También puedes crear las tres subcarpetas y subir archivo por archivo. `GUIA.md`, `LICENCIA-TAILWIND.txt` y `certamen.sql` se quedan en tu equipo. [Carga de sitios en InfinityFree](https://forum.infinityfree.com/t/how-to-migrate-your-website-to-infinityfree/49315/)
6. Activa la visualización de archivos ocultos en el gestor y confirma los tres archivos `.htaccess`, uno en la raíz y los otros en `img/` y `uploads/`. Deja `uploads/` con permisos de escritura para PHP según la configuración del hosting. Si hay una página de bienvenida `index.html`, renómbrala si interfiere con `index.php`. Abre `https://TU-DOMINIO/index.php` y verifica que se muestre **Acceso de estudiantes** antes de la galería.
7. Sube el logo oficial autorizado en PNG con el nombre exacto `logo-senati.png` a `htdocs/img/`. El encabezado y el pie detectan el archivo automáticamente. Antes de subirlo, ambos muestran el nombre SENATI como texto.
8. En **Organizadores**, ingresa con usuario `admin` y contraseña temporal `Senati-Temporal-2026!`. Despliega **Primer ingreso** e introduce tu clave de instalación. El sistema exige cambiar la contraseña antes de habilitar la gestión. Usa una contraseña única de al menos 8 caracteres y hasta 72 bytes. La clave de instalación deja de intervenir en el inicio de sesión tras el cambio. Puedes vaciarla en `conexion.php` una vez verificado el nuevo acceso.
9. Desde el panel, registra el nombre y la categoría de cada participante y sube de una a cinco fotos JPG, PNG o WebP. Cada archivo admite hasta 4 MB y 16 megapíxeles. Si un iPhone entrega un archivo HEIC, convierte la imagen a JPG antes de cargarla. [Límites de archivos en InfinityFree](https://forum.infinityfree.com/t/file-size-and-upload-limits/49308)
10. Para habilitar el acceso oficial, registra una aplicación web autorizada para las cuentas de SENATI en Microsoft Entra. Configura el retorno exacto https://certamen-software-senati.kesug.com/senati.php y el permiso delegado Microsoft Graph User.Read. En el archivo privado conexion.php del servidor, define las constantes SENATI_CLIENT_ID y SENATI_CLIENT_SECRET con el identificador de aplicación y el valor de su secreto. Usa un canal privado para configurar el secreto. PHP necesita cURL con HTTPS.
11. Prueba con una cuenta estudiantil institucional. El primer acceso registra al votante y conserva cualquier voto previo asociado a su correo. En el panel puedes abrir o cerrar las votaciones y programar la fecha y hora de cierre en horario de Lima.

[Documentación de Microsoft Entra](https://learn.microsoft.com/en-us/graph/auth-register-app-v2)
