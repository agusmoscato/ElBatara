Esta carpeta es el codigo fuente "canonico" de desarrollo, con la
estructura ideal (config/ e includes/ FUERA de public_html).

NO subir esto a Hostinger. Para desplegar el sistema, usar la carpeta
DEPLOY_HOSTINGER/ que está en la raíz del proyecto (un nivel arriba de
esta carpeta), que ya tiene todo reacomodado y las rutas ajustadas
para funcionar dentro de public_html sin necesitar acceso FTP a nivel
superior.

Si en algún momento se vuelve a tener acceso FTP/SSH con permiso para
crear carpetas fuera de public_html, esta es la estructura a usar en
su lugar (más segura, porque config/ e includes/ ni siquiera quedan
dentro de la carpeta pública del servidor).
