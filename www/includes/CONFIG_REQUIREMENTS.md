# Datos necesarios para completar `config.php`

Si quieres que alguien más rellene `www/includes/config.php` por ti, prepara esta información para cada una de las cinco webs (`descoberta`, `can-pere`, `cal-mata`, `can-foix`, `el-ginebro`):

1. **URL base del WordPress/WooCommerce**  
   Ejemplo: `https://mi-sitio.com` sin barra final.

2. **Credenciales de API** (elige uno de los dos métodos):
   - **WooCommerce REST API**: `consumer_key` y `consumer_secret` generados en WooCommerce → Ajustes → Avanzado → REST API.
   - **Basic Auth de WordPress**: usuario y contraseña (`basic_user`, `basic_password`) válidos para llamadas a `wp-json/...`.

3. **IDs de categorías de WooCommerce**  
   Números de las categorías que debe usar el sync en cada sitio: `activitat-de-dia`, `centre-interes`, `cases-de-colonies`.

4. **Método preferido para suministrar credenciales**  
   Indica si irán como variables de entorno (`DESCOBERTA_BASE_URL`, `DESCOBERTA_CONSUMER_KEY`, etc.) o si se deben escribir directamente en `config.php`.

Con esos datos es posible completar todas las entradas de `$SITE_APIS` en `config.php` y dejar listas las llamadas a las APIs de cada web.
