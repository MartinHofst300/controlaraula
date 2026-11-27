# Control de Aula

Sistema IoT Multi-Aula para el control remoto de **iluminación (relé 220V)**, **aire acondicionado (IR)** y **proyector multimedia (IR)** mediante una **PWA (Progressive Web App)** centralizada en PHP/MariaDB + Docker y módulos **ESP32**.

---

## Acceso en Producción

- **Aplicación Web / PWA (HTTPS):** `https://40-160-138-65.nip.io` *(instalable en Android, iOS y escritorio)*
- **Endpoint directo para ESP32 (HTTP):** `http://40.160.138.65:8085/api/esp32/sincronizar.php`

---

## Características Principales

### 1. Frontend Web y PWA Completa (Bootstrap 5.3 + Bootstrap Icons)
- **Progressive Web App (PWA) instalable:** HTTPS con certificado Let's Encrypt (Nginx Proxy Manager), `manifest.json` (`display: standalone`, íconos `192x192`, `512x512`, `maskable` y `SVG`), Service Worker (`sw.js`) con caché inteligente (Network-First para assets propios, Cache-First para CDN y Network-Only para las APIs en tiempo real) y pantalla `offline.html` con acceso directo al **Modo Respaldo Local (Puerto 80)** del ESP32.
- **Interfaz adaptable y accesible:** Diseño 100% responsive para celulares, tablets y PC, tema **Claro / Oscuro / Automático**, paleta de colores personalizable, escala de texto, alto contraste y selector de unidad de temperatura (**°C / °F**).
- **Control rápido por aula:** Escenas en 1 clic (*Modo Proyección*, *Iniciar Clase*, *Cerrar Aula*), encendido/apagado de aire acondicionado, control de luces y control completo de proyector (fuentes HDMI 1 / HDMI 2 / VGA, congelar imagen, pantalla en negro y volumen).

### 2. Panel de Administración Escolar
- **Resumen en tiempo real (KPIs):** Indicadores rápidos de espacios online, luces encendidas, aires activos y proyectores activos.
- **Tablero Multi-Aula con filtros y apagado selectivo:** Buscador por nombre, filtro por tipo de espacio o estado, y apagado global general o por servicio (*Apagar Luces*, *Apagar Aires*, *Apagar Proyectores*, *Apagar Todo*).
- **Tipos de espacio personalizados:** Alta y gestión de categorías escolares (*Aula*, *Laboratorio*, *Taller*, *SUM*, *Biblioteca*, *Dirección*, etc.) con íconos propios.
- **Gestión simplificada de espacios:** Al dar de alta un espacio se crea automáticamente su cuenta de acceso (usando el nombre del espacio), su token interno de hardware y se clonan automáticamente los códigos y protocolos IR.
- **Horarios de cierre automático:** Reglas programables por hora, días de la semana y servicio para ahorro energético al finalizar cada turno, con botón de ejecución de prueba inmediata.
- **Gestión de Administradores:** Alta, edición y control de cuentas administrativas adicionales (Dirección, Regencia, Preceptoría).
- **Registro de Eventos y Exportación:** Auditoría filtrable por espacio, tipo de evento o texto, con exportación directa a **Excel (CSV UTF-8)**.

### 3. Firmware ESP32 (`v2.0.0-BGH`)
- **Multi-WiFi automático:** Soporte para hasta 6 redes Wi-Fi con reconexión automática no bloqueante.
- **Sincronización con servidor + Modo Respaldo Local:** Polling HTTP cada 1,5 s autenticado mediante `X-API-Key` por aula, y servidor web local embebido en memoria Flash (`PROGMEM`, puerto 80) para operar sin conexión a Internet.
- **Hardware soportado:**
  - **Relé de iluminación:** `GPIO 26` (activo en ALTO).
  - **Emisor IR (LEDs IR con transistor BC337):** `GPIO 18` — Proyector **Epson** (`NEC` 32-bit, 38 kHz, doble pulso de apagado con pausa de 2 s) y Aire Acondicionado **BGH / Star Series** (`COOLIX` 24-bit, 38 kHz).
  - **Receptor IR (KY-022):** `GPIO 15` (buffer 1024 muestras).
  - **LED indicador de estado:** `GPIO 2`.

---

## Estructura del Proyecto

```text
├── README.md
├── hosting/                           # Código listo para producción (Docker / Apache + PHP + MariaDB)
│   ├── Dockerfile
│   ├── docker-compose.yml
│   ├── app/                           # Configuración, conexión PDO, autenticación y helpers
│   ├── public/                        # Panel web, PWA (manifest.json, sw.js, offline.html) y API REST
│   └── sql/
│       └── schema.sql                 # Esquema completo de MariaDB/MySQL (aulas, tipos, horarios, usuarios, IR, logs)
├── hosting.zip                        # Paquete sincronizado de despliegue
└── control-aula/
    ├── carcasa_3d/                    # Archivos STL, OpenSCAD, planos de conexión y visor 3D
    ├── esp32/
    │   └── control_aula/
    │       ├── control_aula.ino       # Firmware principal del ESP32
    │       └── config.h               # Redes Wi-Fi, pines, servidor y códigos IR de respaldo
    └── servidor/                      # Espejo del código del servidor web y schema.sql
```

---

## Credenciales de Acceso por Defecto

| Rol | Usuario | Contraseña | Permisos |
| :--- | :--- | :--- | :--- |
| **Administrador** | `admin` | `admin123` | Acceso total: tablero multi-aula, tipos de espacio, alta/edición de salones, horarios automáticos, administradores y auditoría |
| **Aula 101** | `Aula 101` *(o `aula101`)* | `aula123` | Control exclusivo del Aula 101 |
| **Laboratorio 1** | `Laboratorio 1 - Informática` *(o `lab1`)* | `lab123` | Control exclusivo del Laboratorio 1 |
| **Taller 1** | `Taller de Electrónica` *(o `taller1`)* | `taller123` | Control exclusivo del Taller de Electrónica |

---

## Puesta en Marcha Rápida

### Servidor (Docker)
1. Ir a la carpeta `hosting/` y ejecutar:
   ```bash
   docker compose up -d --build
   ```
2. La base de datos `control_aula` se inicializa automáticamente con `hosting/sql/schema.sql` (y las migraciones automáticas en `hosting/app/funciones.php`).
3. Para habilitar la PWA completa con HTTPS, apuntar un proxy reverso (ej. Nginx Proxy Manager con Let's Encrypt) al puerto `80` del contenedor `control-aula-web`.

### ESP32 (Arduino IDE)
1. Abrir `control-aula/esp32/control_aula/control_aula.ino`.
2. Instalar las librerías **IRremoteESP8266** y **ArduinoJson**.
3. Revisar las credenciales Wi-Fi, `SERVER_BASE_URL` y `API_KEY` en `config.h` y cargar en la placa **ESP32 DevKit V1**.
