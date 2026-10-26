# Wookie Books API

API REST de librería (bookstore) construida con **Laravel 13** y PHP 8.3+, donde los autores publican sus aventuras y los lectores las navegan sin autenticarse. El enunciado original de la asignación está en [`docs/assignment.md`](docs/assignment.md).

## Requisitos

- PHP 8.3 o superior (con `pdo_sqlite`, `mbstring` y `xml`)
- Composer 2

La base de datos por defecto es SQLite, así que no hace falta instalar nada más.

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

También puedes ejecutar todo lo anterior con `composer setup`.

## Ejecución

```bash
php artisan serve
```

La API queda disponible en `http://localhost:8000`.

### Configuración

| Variable     | Descripción                                                                  | Default             |
|--------------|------------------------------------------------------------------------------|---------------------|
| `JWT_SECRET` | Secreto con el que se firman los tokens (HS256). Si está vacío usa `APP_KEY`. | `APP_KEY`           |
| `JWT_TTL`    | Minutos de vigencia del token                                                | `60`                |

En producción define un `JWT_SECRET` propio.

### Datos semilla

`php artisan migrate --seed` crea los usuarios y libros del enunciado (es idempotente, se puede repetir):

| Usuario       | Contraseña       | Seudónimo    |
|---------------|------------------|--------------|
| `lohgarra`    | `rwaaaaawr`      | Lohgarra     |
| `chewbacca`   | `rrwwwgggh`      | Chewbacca    |
| `darth_vader` | `iwanttopublish` | Darth Vader  |

| ID | Título                      | Autor     | Publicado | Eliminado |
|----|-----------------------------|-----------|-----------|-----------|
| 1  | Adventures on Kashyyyk      | lohgarra  | sí        | no        |
| 2  | Life Debt: A Wookie Story   | lohgarra  | sí        | no        |
| 3  | My Draft Manuscript         | lohgarra  | no        | no        |
| 4  | Deleted Memories            | lohgarra  | sí        | sí        |
| 5  | Smuggler's Run              | chewbacca | sí        | no        |

## Pruebas

```bash
composer test
# o
php artisan test
```

Las pruebas usan SQLite en memoria y cubren autenticación, autorización, validación, borrado lógico, búsqueda y filtro por autor, la regla de Darth Vader, la negociación de formato y los datos semilla.

## Endpoints

| Método   | Ruta              | Auth | Descripción                                                  |
|----------|-------------------|------|--------------------------------------------------------------|
| `POST`   | `/auth/token`     | no   | Login con usuario/contraseña, devuelve un JWT                |
| `GET`    | `/books`          | no   | Libros publicados y no eliminados. Filtros `search` y `author` |
| `GET`    | `/books/{id}`     | no   | Detalle de un libro público                                  |
| `GET`    | `/me/books`       | sí   | Libros no eliminados del usuario (borradores y publicados)   |
| `POST`   | `/me/books`       | sí   | Crea un libro como borrador                                  |
| `GET`    | `/me/books/{id}`  | sí   | Detalle de un libro propio (opcional)                        |
| `PATCH`  | `/me/books/{id}`  | sí   | Actualiza campos, incluido `published`                       |
| `DELETE` | `/me/books/{id}`  | sí   | Borrado lógico (`deleted_at`), responde `204`                |

### Ejemplos

```bash
# Login
curl -s -X POST localhost:8000/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"username": "lohgarra", "password": "rwaaaaawr"}'
# {"access_token": "eyJ...", "token_type": "bearer"}

TOKEN=eyJ...

# Catálogo público con filtros (insensibles a mayúsculas)
curl 'localhost:8000/books?search=wookie&author=lohgarra'

# Crear un borrador y publicarlo
curl -X POST localhost:8000/me/books \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"title": "My story", "description": "...", "price": "9.99"}'

curl -X PATCH localhost:8000/me/books/6 \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"published": true}'

# Formato XML
curl -H 'Accept: application/xml' localhost:8000/books/1
```

### Búsqueda y filtros

- `search`: subcadena, sin distinguir mayúsculas, contra `title` o `description`. Los comodines `%` y `_` se tratan literalmente.
- `author`: coincidencia exacta, sin distinguir mayúsculas, contra el seudónimo del autor.
- Ambos se combinan con AND.

### Campos de un libro

Los endpoints públicos devuelven `id`, `title`, `description`, `author_pseudonym`, `cover_image_url`, `price` y `published`. Los endpoints autenticados añaden `deleted_at`. `price` siempre es un string con dos decimales (`"9.99"`).

Al crear: `title`, `description` y `price` son obligatorios; `cover_image_url` es opcional y debe ser una URL. Enviar `"published": true` en el `POST` responde `400` (usa `PATCH` para publicar).

### Formato de respuesta

JSON por defecto. Con el header `Accept` se negocia el formato:

| `Accept`                          | Resultado                  |
|-----------------------------------|----------------------------|
| ausente, `*/*`, `application/json` | JSON                       |
| `application/xml`                 | XML                        |
| cualquier otro (p. ej. `text/csv`) | `406 Not Acceptable`       |

Se respetan los valores `q` (`application/xml;q=0.9, application/json;q=0.5` devuelve XML). Los errores siempre se devuelven en JSON, como exige el contrato de errores.

### Errores

Toda respuesta que no sea 2xx tiene este formato:

```json
{
  "error": "validation_error",
  "message": "The title field is required.",
  "details": {
    "field": "title",
    "code": "required",
    "errors": {"title": ["The title field is required."]}
  }
}
```

| Status | `error`              | Cuándo                                                       |
|--------|----------------------|--------------------------------------------------------------|
| 400    | `validation_error`   | Datos inválidos o faltantes                                  |
| 401    | `unauthorized`       | Credenciales inválidas, token ausente, inválido o expirado   |
| 403    | `forbidden`          | El libro no es del usuario, o Darth Vader intenta publicar   |
| 404    | `not_found`          | Libro inexistente, eliminado o (en público) no publicado     |
| 405    | `method_not_allowed` | Método no soportado en la ruta                               |
| 406    | `not_acceptable`     | Formato solicitado en `Accept` no soportado                  |

`details` solo aparece en errores de validación.

### Regla de Darth Vader

El usuario `darth_vader` no puede publicar libros. Cualquier `PATCH` con `"published": true` responde:

```json
{"error": "forbidden", "message": "Darth Vader is not allowed to publish on Wookie Books"}
```

Puede crear, editar, despublicar y eliminar sus borradores con normalidad.

## Decisiones de diseño

- **JWT**: tokens HS256 firmados con [`firebase/php-jwt`](https://github.com/firebase/php-jwt). Un guard propio (`App\Auth\JwtGuard`, registrado con `Auth::viaRequest`) resuelve al usuario desde el `Bearer` token, de modo que `auth` funciona como en cualquier app Laravel. No hay sesiones ni cookies.
- **Usuario personalizado**: el modelo `User` usa `username` + `author_pseudonym` en lugar de `name`/`email`.
- **Borrado lógico**: se usa el trait `SoftDeletes` de Eloquent sobre la columna `deleted_at`, por lo que los libros eliminados quedan excluidos de cualquier consulta por defecto (y devuelven `404` al accederlos directamente).
- **Autorización**: `BookPolicy` decide la propiedad del libro y la regla de Darth Vader (`publish`). La propiedad se verifica antes de validar el cuerpo, para que un tercero reciba `403` y no detalles de validación.
- **Errores**: `App\Exceptions\ApiExceptionRenderer` convierte cualquier excepción al formato de error de la API. La validación responde `400` (no el `422` por defecto de Laravel) como pide el enunciado.
- **Negociación de formato**: `NegotiateContentType` se ejecuta antes de la autenticación y convierte las respuestas JSON exitosas a XML con `App\Support\XmlEncoder`. Las listas usan elementos `<item>` y los valores `null` se marcan con `nil="true"`. Los headers de navegador (`text/html,...,application/xml;q=0.9,*/*;q=0.8`) se interpretan como JSON.
- **Rutas sin prefijo**: se registran en `routes/api.php` con `apiPrefix: ''` para que coincidan con `/books`, `/me/books` y `/auth/token`.
- **Listados sin paginar**: el contrato pide un arreglo JSON, por lo que el listado devuelve todos los libros que coinciden.

## Estructura

```
app/
├── Auth/JwtGuard.php                  # resuelve el usuario desde el Bearer token
├── Exceptions/ApiExceptionRenderer.php
├── Http/
│   ├── Controllers/                   # Auth, Book (público) y MeBook (autor)
│   ├── Middleware/NegotiateContentType.php
│   ├── Requests/                      # Login, StoreBook, UpdateBook
│   └── Resources/                     # PublicBookResource, BookResource
├── Models/                            # User, Book
├── Policies/BookPolicy.php
├── Rules/NotPublishedOnCreate.php
├── Services/JwtService.php
└── Support/XmlEncoder.php
database/                              # migraciones, factories y seeders
tests/                                 # Feature (endpoints) y Unit (JWT, XML)
```
