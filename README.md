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
│           └── schema.sql        # Único archivo de esquema completo y datos iniciales
└── control-aula.zip              # Archivo ZIP completo empaquetado para despliegue
```

## Credenciales de Acceso por Defecto

| Rol | Usuario | Contraseña | Espacio Asignado / Permisos |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin` | `admin123` | Supervisión general de todos los salones, gestión de espacios, catálogo IR, auditoría y simulador ESP32 |
| **Aula 101** | `aula101` | `aula123` | Control exclusivo de terminal para Aula 101 - General |
| **Laboratorio 1** | `lab1` | `lab123` | Control exclusivo de terminal para Laboratorio 1 - Informática |
| **Taller 1** | `taller1` | `taller123` | Control exclusivo de terminal para Taller de Electrónica |
| **Profesor de Prueba** | `profesor` | `profe123` | Cuenta docente con acceso de prueba |

## Equipos de Aula Soportados (Hardware Real)

### 1. Proyector Multimedia EPSON
- **Protocolo:** `NEC` (32 bits, 38 kHz).
- **Encendido (`prender`):** `0x8355906F` (1 pulso, pausa 100 ms).
- **Apagado (`apagar`):** `0x8355906F` (**2 repeticiones con 2000 ms de pausa**). Epson proyecta el diálogo en pantalla de confirmación: *"¿Desea apagar el proyector? Presione el botón de encendido nuevamente para confirmar"*. El sistema envía automáticamente el segundo pulso confirmatorio tras 2 segundos.
- **Entradas y Funciones:**
  - `hdmi1`: `0x8355D926`
  - `hdmi2`: `0x835530CF`
  - `vga`: `0x8355659A`
  - `freeze` (congelar imagen): `0x8355926D`
  - `av_mute` (pantalla en negro): `0x8355936C`
  - `vol_mas`: `0x835552AD`
  - `vol_menos`: `0x8355728D`

### 2. Aire Acondicionado Star Series (Coolix OEM / TCL / Midea)
- **Protocolo:** `COOLIX` (24 bits, 38 kHz). Compatible con marcas Star-Trak, StarVision, CoolStar y placas universales.
- **Trama y Estado (`ac_state`):** El sistema despacha el estado completo del termostato `{ "marca": "Star (OEM)", "protocolo": "COOLIX", "power": true/false, "temp": 16..30, "modo": "frio|calor|ventilacion|seco|auto", "fan": "auto|bajo|medio|alto", "swing": true/false }`.
- **Códigos Principales:**
  - `prender` / `frio_24`: `0xB2BF40`
  - `apagar`: `0xB27BE0`
  - `frio_18`: `0xB2BF00`
  - `frio_20`: `0xB2BF10`
  - `frio_22`: `0xB2BF20`
  - `frio_26`: `0xB2BF60`
  - `calor_21`: `0xB2BF95`
  - `calor_26`: `0xB2BF9C`
  - `modo_ventilacion`: `0xB2BFC8`
  - `modo_seco`: `0xB2BF84`
  - `modo_auto`: `0xB2BF08`
  - `swing`: `0xB26BE0`

## Puesta en Marcha

Consultar el archivo [control-aula/INSTALL.txt](control-aula/INSTALL.txt) para las instrucciones detalladas de configuración de base de datos, despliegue del servidor y compilación del firmware.
