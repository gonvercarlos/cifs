# CIF Management System

Sistema de gestión de CIFs (Números de Identificación Fiscal) para clientes.

## Requisitos

- Docker
- Docker Compose

## Configuración

1. Copia el archivo de ejemplo de variables de entorno:
   ```bash
   cp .env.example .env
   ```

2. Edita el archivo `.env` con tus credenciales de base de datos:
   ```env
   DB_HOST=tu_host
   DB_PORT=5432
   DB_NAME=tu_base_datos
   DB_USER=tu_usuario
   DB_PASS=tu_contraseña
   BASE_PATH=
   ```

   **BASE_PATH**: Si vas a ejecutar la aplicación en una subcarpeta (por ejemplo, `http://dominio.com/cifs`), establece `BASE_PATH=/cifs`. Déjalo vacío si se ejecuta en la raíz.

## Desarrollo

Para ejecutar el proyecto en modo desarrollo (con volúmenes montados para cambios en tiempo real):

```bash
docker-compose up -d
```

La aplicación estará disponible en: http://localhost:2020

Para ver los logs:
```bash
docker-compose logs -f
```

Para detener el contenedor:
```bash
docker-compose down
```

## Producción

Para ejecutar el proyecto en modo producción:

```bash
docker-compose -f docker-compose.prod.yml up -d
```

El modo producción:
- No monta volúmenes (la aplicación se copia dentro del contenedor)
- Se conecta a la red externa `npm-network`
- Reinicia automáticamente el contenedor si falla

Para detener:
```bash
docker-compose -f docker-compose.prod.yml down
```

## Estructura del Proyecto

- `cifs.php` - Interfaz principal con listado de clientes y CIFs
- `action.php` - Backend para operaciones CRUD (añadir, editar, eliminar)
- `config.php` - Configuración de base de datos y sesión
- `Dockerfile` - Configuración de contenedor Docker
- `docker-compose.yml` - Configuración para desarrollo
- `docker-compose.prod.yml` - Configuración para producción

## Características

- Gestión de CIFs asociados a clientes
- Búsqueda por nombre de cliente, CIF o razón social
- Paginación (100, 200, 500 o todos los registros)
- Protección CSRF en todas las operaciones POST
- Interfaz modal para añadir/editar/eliminar CIFs
- Modo debug para operaciones de eliminación

## Puertos

- **Desarrollo y Producción**: 2020

## Notas de Seguridad

- Las credenciales de base de datos se manejan mediante variables de entorno
- El archivo `.env` está excluido del control de versiones por `.gitignore`
- Todas las operaciones POST están protegidas con tokens CSRF
- Se utilizan consultas preparadas para prevenir inyección SQL
