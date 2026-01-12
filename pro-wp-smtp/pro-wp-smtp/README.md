# Pro WP SMTP

Plugin SMTP completo para WordPress con todas las funcionalidades profesionales activadas.

## Descripción

Pro WP SMTP es un plugin completo para configurar el envío de correos electrónicos desde WordPress usando SMTP. Incluye soporte para servidores SMTP corporativos y servicios populares como Gmail, SendGrid y Mailgun.

## Características

✅ **Todas las funciones PRO activadas sin limitaciones:**

- **Configuración SMTP Corporativa Completa:**
  - Host SMTP personalizado
  - Puerto configurable (25, 465, 587)
  - Encriptación (None, SSL, TLS)
  - Autenticación SMTP
  - Usuario y contraseña

- **Múltiples Proveedores:**
  - SMTP Genérico (para correos corporativos)
  - Gmail
  - SendGrid
  - Mailgun

- **Funciones Profesionales:**
  - Email Logging - Registro completo de emails enviados
  - Email Tracking - Seguimiento de emails
  - Reportes y Estadísticas
  - Test de envío de correos

## Instalación

1. Descargar el archivo `pro-wp-smtp.zip`
2. Ir a WordPress Admin → Plugins → Añadir nuevo → Subir plugin
3. Seleccionar el archivo ZIP y hacer clic en "Instalar ahora"
4. Activar el plugin

## Configuración

### Para Correo Corporativo (SMTP Genérico)

1. Ir a **Pro WP SMTP → Configuración**
2. Configurar **From Email** y **From Name**
3. Seleccionar **Mailer**: "Otro SMTP (Para correos corporativos)"
4. Configurar los datos SMTP:
   - **SMTP Host**: smtp.tudominio.com
   - **SMTP Port**: 587 (para TLS) o 465 (para SSL)
   - **Encryption**: TLS (recomendado)
   - **Authentication**: Activar
   - **SMTP Username**: tu-usuario@tudominio.com
   - **SMTP Password**: tu-contraseña
5. Guardar configuración

### Para Gmail

1. Seleccionar **Mailer**: "Gmail"
2. Configurar Client ID y Client Secret de la API de Google

### Para SendGrid / Mailgun

1. Seleccionar el proveedor correspondiente
2. Ingresar la API Key proporcionada por el servicio

## Prueba de Envío

1. Ir a **Pro WP SMTP → Email Test**
2. Ingresar un correo de destino
3. Hacer clic en "Enviar Email de Prueba"
4. Verificar que el correo llegue correctamente

## Registro de Emails

Ver todos los emails enviados en **Pro WP SMTP → Email Log**

## Reportes y Estadísticas

Ver estadísticas de envío en **Pro WP SMTP → Reportes**

## Requisitos

- WordPress 5.2 o superior
- PHP 7.4 o superior

## Licencia

GPL v2 or later

## Soporte

Para soporte o consultas sobre el plugin, contactar al administrador del sitio.

## Changelog

### 1.0.0
- Versión inicial
- Configuración SMTP completa para correos corporativos
- Soporte para Gmail, SendGrid y Mailgun
- Email logging
- Reportes y estadísticas
- Todas las funciones PRO activadas
