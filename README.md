# Control de Aula

Sistema de control remoto para aula: gestión de luces (relé) y equipos infrarrojos (aire acondicionado y proyector) a través de un servidor web central y un microcontrolador ESP32.

## Características

- **Servidor Web (PHP / MariaDB)**:
  - Panel web responsivo con tema oscuro (#1c1818 y acento #897c37) y actualización en tiempo real cada 2 segundos.
  - Control de luces y equipos infrarrojos (aire acondicionado y proyector).
  - Soporte de tramas infrarrojas completas: protocolos estándar, tramas de estado hex (climatizadores) y secuencias raw en microsegundos.
  - Asignación de capturas IR a códigos de dispositivos en un solo paso.
  - API REST para sincronización periódica con el ESP32 y respaldo local.

- **Firmware ESP32 (Arduino IDE)**:
  - Polling seguro al servidor web para recibir comandos pendientes y reportar telemetría.
  - Conexión TLS persistente (`WiFiClientSecure` con `setReuse(true)`).
  - Receptor IR con buffer de 1024 muestras para capturar tramas de aire acondicionado.
  - Emisor IR compatible con comandos cortos, tramas largas y pulsos raw con repeticiones y pausas configurables.
  - Servidor web local de respaldo en puerto 80 con interfaz de control integrada en PROGMEM.
  - Prioridad de control local de luces ante desconexión del servidor.

## Estructura del Proyecto

```text
├── control-aula/
│   ├── INSTALL.txt               # Guía rápida de despliegue
│   ├── esp32/
│   │   └── control_aula/
│   │       ├── control_aula.ino  # Firmware principal para ESP32
│   │       └── config.h          # Configuración de red, pines y servidor
│   └── servidor/
│       ├── app/                  # Configuración, base de datos y autenticación
│       ├── public/               # Panel web, simulador y endpoints de API
│       └── sql/
│           ├── schema.sql        # Esquema completo de base de datos
│           └── migracion.sql     # Script de migración para bases existentes
└── control-aula.zip              # Archivo ZIP completo empaquetado para despliegue
```

## Credenciales de Acceso por Defecto

| Rol | Usuario | Contraseña | Permisos |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin` | `admin123` | Control total del aula, gestión de usuarios, códigos IR, capturas KY-022, logs y simulador ESP32 |
| **Profesor** | `profesor` | `profe123` | Control directo del aula (iluminación, aire acondicionado y proyector) y configuración visual |

## Puesta en Marcha

Consultar el archivo [control-aula/INSTALL.txt](control-aula/INSTALL.txt) para las instrucciones detalladas de configuración de base de datos, despliegue del servidor y compilación del firmware.
