# GUÍA MAESTRA DE DEFENSA TÉCNICA — PROYECTO CONTROL DE AULA IoT

Este documento reúne **absolutamente toda la explicación técnica, electrónica, de programación, servidor, redes, diseño 3D y diccionario de siglas** para defender el proyecto en el colegio y en la Expo.

---

## 1. ¿QUÉ ES EL PROYECTO Y CÓMO FUNCIONA DE PUNTA A PUNTA?

Es un **sistema domótico centralizado Multi-Aula** basado en **IoT (Internet de las Cosas)** que permite controlar desde cualquier celular o computadora:
1. **La Iluminación del aula (220V AC)** mediante un módulo de **Relé**.
2. **El Aire Acondicionado (BGH / Star Series)** mediante emisión infrarroja (**Protocolo COOLIX de 24 bits**).
3. **El Proyector Multimedia (Epson)** mediante emisión infrarroja (**Protocolo NEC de 32 bits**, incluyendo encendido, apagado con doble pulso de confirmación, cambio de entrada HDMI/VGA, Freeze, A/V Mute y Volumen).

### El recorrido de una orden (Arquitectura Cliente-Servidor):
1. **El Usuario (Celular / PC):** Entra al panel web responsive, inicia sesión y toca un botón (por ejemplo, *"Encender Aire"* o la escena rápida *"Iniciar Clase"*).
2. **El Servidor en la Nube (VPS con Docker):** El navegador envía una petición HTTP `POST` en formato `JSON` a la API en PHP (`api/aire.php`, `api/luces.php` o `api/proyector.php`). El servidor verifica la sesión y el token de seguridad (`CSRF`) y guarda la orden en la base de datos **MySQL / MariaDB** (tabla `estado_aula` y tabla `cola_ir` con estado `pendiente`).
3. **El Nodo del Aula (ESP32):** Cada **1,5 segundos** (`INTERVALO_POLLING_MS = 1500`), el ESP32 consulta por Wi-Fi al endpoint `api/esp32/sincronizar.php` enviando su `API Key` de aula.
4. **Ejecución y Confirmación (`ACK`):** El servidor le entrega el estado de las luces y el código infrarrojo pendiente. El ESP32 conmuta el relé o dispara los 2 LEDs infrarrojos a través del transistor `BC337`, y en la siguiente petición le envía al servidor el parámetro `ir_ejecutado` con el ID del comando para que el servidor lo marque como completado.

---

## 2. DICCIONARIO COMPLETO DE SIGLAS (LO QUE PREGUNTA EL PROFE)

### A. Siglas de Electrónica, Placas y Componentes
* **`ESP32` / `NodeMCU`:**
  * **`ESP`:** Por *Espressif Systems* (la empresa que fabrica el microchip).
  * **`MCU`:** *Microcontroller Unit* (**Unidad de Microcontrolador**). `NodeMCU` significa *Nodo Microcontrolador* para IoT.
* **`IoT`:** *Internet of Things* (**Internet de las Cosas**): objetos físicos cotidianos conectados a internet.
* **`GPIO`:** *General Purpose Input/Output* (**Entrada/Salida de Propósito General**). Son los pines programables del chip que podemos configurar por código como entradas (leer sensores) o salidas (activar componentes).
* **`VCC`:** *Voltage at the Common Collector* (**Voltaje de Colector Común**). Es el pin de alimentación positiva (`+5V` o `+3.3V`).
* **`VIN`:** *Voltage In* (**Entrada de Voltaje**). Es el pin del ESP32 que recibe `5V` desde una fuente externa (o entrega `5V` cuando el ESP32 está enchufado por USB).
* **`GND`:** *Ground* (**Tierra / Masa**). Es la referencia común de `0 Voltios` (negativo) de todo el circuito.
* **`AC`:** *Alternating Current* (**Corriente Alterna**). Es la electricidad de la red domiciliaria (`220V` a `50 Hz`), donde la corriente cambia de sentido 50 veces por segundo y no tiene polaridad fija.
* **`DC`:** *Direct Current* (**Corriente Continua**). Es la electricidad estable de `5V` o `3.3V` donde siempre hay un polo positivo (`+`) y uno negativo (`-`).
* **`HLK-5M05` y `+Vo` / `-Vo`:**
  * **`HLK`:** *Hi-Link* (fabricante de la fuente).
  * **`5M`:** *5 Watts Miniatura* (potencia máxima: `5W`, entrega hasta `1 Ampere`).
  * **`05`:** *5 Voltios* de salida continua.
  * **`+Vo` y `-Vo`:** *Voltage Output* (**Salida de Voltaje** positiva `+5V` y negativa `GND`).
* **Puertos del Relé (`COM`, `NO` / `NA`, `NC`):**
  * **`COM`:** *Common* (**Común**). Borne central donde entra la Fase de 220V.
  * **`NO` (en inglés) / `NA` (en español):** *Normally Open* (**Normalmente Abierto**). En reposo está desconectado; cuando el relé se activa, se une con `COM` y enciende la lámpara.
  * **`NC`:** *Normally Closed* (**Normalmente Cerrado**). En reposo está conectado y se abre al activar el relé (no se usa en nuestro proyecto).
* **`LED`:** *Light Emitting Diode* (**Diodo Emisor de Luz**). Como todo diodo, tiene polaridad: deja pasar la corriente solo desde el **Ánodo (`+`, pata larga)** hacia el **Cátodo (`-`, pata corta)**.
* **`BC337` (`BJT NPN`):**
  * **`BJT`:** *Bipolar Junction Transistor* (**Transistor de Unión Bipolar**).
  * **`NPN`:** *Negative-Positive-Negative* (**Negativo-Positivo-Negativo**). Indica la estructura de las 3 capas de silicio internas: conduce entre Colector y Emisor cuando recibe tensión positiva en la Base.
  * **`BC337`:** Según el código europeo *Pro Electron*, la **`B`** indica semiconductor de **Silicio** y la **`C`** indica transistor de baja frecuencia / conmutación.
* **`KY-022` / `KY-005`:** **`KY`** es el prefijo de la marca *Keyes* para su línea de módulos sensores de Arduino/ESP32.

### B. Siglas de Infrarrojo y Protocolos de Comunicación
* **`IR`:** *Infrared* (**Infrarrojo**). Luz invisible al ojo humano (`940 nm` de longitud de onda).
* **`NEC`:** *Nippon Electric Company* (**Compañía Eléctrica Japonesa**). Empresa que creó el protocolo infrarrojo estándar de **32 bits** a **38 kHz** que utiliza nuestro **Proyector Epson**.
* **`COOLIX`:** Nombre técnico e industrial del protocolo infrarrojo de **24 bits** desarrollado por el fabricante **Midea / Toshiba** (viene de *Cool* = Frío) para placas OEM (*Original Equipment Manufacturer*) de aires acondicionados como **BGH**, **Star** y **Surrey**.
* **`RC5` / `RC6`:** *Remote Control 5 / 6* (**Control Remoto generación 5 y 6**), protocolo infrarrojo creado por **Philips**.
* **`PWM`:** *Pulse Width Modulation* (**Modulación por Ancho de Pulsos**). Técnica con la que el ESP32 enciende y apaga el pin emisor a `38.000 veces por segundo` para modular el haz de luz.
* **`kHz`:** *Kilohertz* (**Kilohertzios** = `1.000 ciclos por segundo`). `38 kHz` son `38.000 destellos por segundo`.
* **`HEX`:** *Hexadecimal* (sistema numérico de base 16, que usa dígitos `0-9` y letras `A-F`, como `0xC1AA09F6`).
* **`RAW`:** Palabra en inglés para **"Crudo / Datos en bruto"** (lista de tiempos en microsegundos de luz y pausa cuando un protocolo no es estándar).

### C. Siglas del Servidor, Nube, Seguridad y Redes
* **`OVH` (`OVHcloud`):** *On Vous Héberge* (en francés: **"Nosotros te alojamos / hospedamos"**; también por el apodo de su fundador Octave Klaba, *Oles Van Herman*). Empresa europea donde está alojado nuestro servidor.
* **`VPS`:** *Virtual Private Server* (**Servidor Privado Virtual**). Máquina virtual con sistema operativo Linux (**Ubuntu Server**) que corre las 24 horas en el centro de datos.
* **`SSH`:** *Secure Shell* (**Consola / Intérprete de Comandos Seguro**). Protocolo encriptado (puerto `22`) para administrar el servidor remoto desde la terminal.
* **`SCP`:** *Secure Copy Protocol* (**Protocolo de Copia Segura**). Herramienta para subir archivos (como `hosting.zip`) al servidor a través de SSH.
* **`HTTP` / `HTTPS`:** *HyperText Transfer Protocol (Secure)* (**Protocolo de Transferencia de Hipertexto [Seguro]**).
* **`API`:** *Application Programming Interface* (**Interfaz de Programación de Aplicaciones**). Conjunto de URLs (`/api/...`) por donde el ESP32 y el navegador intercambian datos con el servidor.
* **`REST`:** *Representational State Transfer* (**Transferencia de Estado Representacional**). Arquitectura web que usa verbos HTTP (`GET` para leer, `POST` para enviar cambios).
* **`JSON`:** *JavaScript Object Notation* (**Notación de Objetos de JavaScript**). Formato liviano de intercambio de datos en texto.
* **`PHP`:** *PHP: Hypertext Preprocessor* (**Preprocesador de Hipertexto**). Lenguaje de programación que corre en el servidor.
* **`SQL`:** *Structured Query Language* (**Lenguaje de Consulta Estructurada**). Lenguaje para manejar la base de datos relacional **MySQL / MariaDB**.
* **`PDO`:** *PHP Data Objects* (**Objetos de Datos de PHP**). Interfaz segura que usamos en PHP con consultas preparadas para evitar ataques de *Inyección SQL*.
* **`CSRF`:** *Cross-Site Request Forgery* (**Falsificación de Petición en Sitios Cruzados**). Protección mediante token secreto para que nadie pueda disparar órdenes desde otra página web.
* **`IP`:** *Internet Protocol* (**Protocolo de Internet**). Dirección numérica única de cada dispositivo en la red.
* **`DHCP`:** *Dynamic Host Configuration Protocol* (**Protocolo de Configuración Dinámica de Host**). Servicio del router que reparte direcciones IP automáticamente a cada celular o ESP32 que se conecta al Wi-Fi.
* **`SSID`:** *Service Set Identifier* (**Identificador del Conjunto de Servicios**). Es el nombre público de la red Wi-Fi.
* **`RSSI`:** *Received Signal Strength Indicator* (**Indicador de Fuerza de la Señal Recibida**), medido en **`dBm`** (*decibelios-milivatio*).

### D. Siglas de Impresión 3D y Diseño
* **`CAD`:** *Computer-Aided Design* (**Diseño Asistido por Computadora**).
* **`STL`:** *Standard Triangle Language* o *Stereolithography* (**Lenguaje Estándar de Triángulos**), formato del modelo 3D.
* **`FDM`:** *Fused Deposition Modeling* (**Modelado por Deposición Fundida**), impresión 3D por filamento fundido capa por capa.
* **`PLA`:** *Polylactic Acid* (**Ácido Poliláctico**), bioplástico derivado del almidón de maíz usado para imprimir la carcasa.

---

## 3. EXPLICACIÓN COMPLETA DE TODAS LAS CONEXIONES Y SOLDADURAS

### A. Pines conectados en el ESP32 (NodeMCU 30 pines)
| Pin en el ESP32 | Qué conectamos ahí | Por qué elegimos ese pin |
| :--- | :--- | :--- |
| **`VIN` (`5V`)** | Cable al **Nodo de 5V** (entrada de resistencias `33 Ω + 100 Ω`) | Es bidireccional: entrega `5V` cuando usamos USB, o recibe `5V` desde la fuente `Hi-Link` cuando está en la pared. |
| **`GND` #1** | Cable al **Nodo de GND** (pata derecha del transistor `BC337`) | Tierra común para el transistor, el Relé y el `-Vo` de la fuente `Hi-Link`. |
| **`3V3` (`3.3V`)** | Pin `VCC` (`+`) del receptor **`KY-022`** | Alimentamos el receptor con `3.3V` para que su pin de salida (`OUT`) entregue pulsos de `3.3V` y no queme la entrada del ESP32 (que es de `3.3V`). |
| **`GND` #2** | Pin `GND` (`-`) del receptor **`KY-022`** | Tierra del módulo receptor infrarrojo. |
| **`GPIO 18` (`D18`)** | Resistencia de `330 Ω` $\rightarrow$ Base del transistor **`BC337`** | Pin con soporte nativo de temporizadores hardware (`PWM`) para modular los `38 kHz` de los LEDs IR. |
| **`GPIO 15` (`D15`)** | Pin `OUT` (`S`) del receptor **`KY-022`** | Pin de entrada con soporte de interrupciones rápidas para medir los microsegundos de cada pulso IR. |
| **`GPIO 26` (`D26`)** | Pin `IN` (`S`) del **Módulo Relé** | Salida digital limpia (no emite pulsos espurios al arrancar el ESP32, evitando que la luz parpadee sola al reiniciar). |

### B. Cómo funciona el Arnés Soldado (Transistor `BC337` + 2 LEDs en Serie + Resistencias)
Mirando el transistor **`BC337` con la cara plana hacia vos** y las 3 patas hacia abajo:
1. **Pata del MEDIO (`Base`):** Recibe los pulsos de `3.3V` del `GPIO 18` a través de una **resistencia de `330 Ω`** (Naranja-Naranja-Marrón), que limita la corriente de base a `~8 mA` para cuidar al ESP32.
2. **Pata DERECHA (`Emisor` — Nodo de GND):** Va soldada directo a `GND` del ESP32, `GND` del Relé y `-Vo` de la fuente `Hi-Link`.
3. **Pata IZQUIERDA (`Colector` — Cadena de los 2 LEDs en Serie):**
   * Del **Nodo de 5V** salen **2 resistencias en paralelo (`33 Ω` + `100 Ω`)**, que forman una resistencia equivalente de **`24,8 Ω`**.
   * De esas resistencias la corriente entra por la **Pata Larga (`+`, Ánodo) del LED 1**.
   * Sale por la **Pata Corta (`-`, Cátodo) del LED 1** y entra directo a la **Pata Larga (`+`, Ánodo) del LED 2**.
   * Sale por la **Pata Corta (`-`, Cátodo) del LED 2** y entra a la **Pata Izquierda (`Colector`) del `BC337`**.
   * **¿Por qué en serie?** Porque en serie la corriente no se divide: el 100% de la corriente atraviesa ambos LEDs por igual, dándole **6 metros de alcance** tanto al LED que apunta al aire como al que apunta al proyector.

### C. Cómo se conectan los `220V` entre la Pared, la Fuente `Hi-Link` y el Relé
* **A la Fuente `Hi-Link` (`AC` y `AC`):** Entran los **2 cables de `220V` de la pared** (`Fase` y `Neutro`) porque necesita estar encendida siempre para darle `5V` (`+Vo` y `-Vo`) al ESP32.
* **Al Relé (`COM` y `NO`):** **¡Nunca van Fase y Neutro juntos al relé!** El relé es una llave que corta **un solo cable** (la Fase que va a la lámpara, o se conecta en paralelo a los 2 tornillos de la tecla de luz de la pared).

---

## 4. LA CARCASA TRIANGULAR IMPRESA EN 3D (FUNDAMENTOS DE DISEÑO)

Si el profe pregunta **por qué diseñaron la carcasa así**:
1. **Geometría Triangular (Esquinera a `45°`):** Al ubicarse en la esquina o pared del aula, la cara frontal diagonal (hipotenusa) apunta naturalmente a `45°` hacia el centro del salón, cubriendo el aire acondicionado y el proyector sin sobresalir como una caja cuadrada.
2. **Rótulas Esféricas Orientables ("Las 2 bolitas"):** Como cada aula tiene el aire y el proyector en distintas alturas y posiciones, diseñamos 2 esferas con túnel central de `5.3 mm` y ranura lateral pasacable que giran como una articulación esférica dentro de sus cunas frontales, permitiendo apuntar cada LED individualmente sin desarmar la caja.
3. **Tabique Aislante Interno:** Una pared interna divide la carcasa en **dos cámaras físicas**: a la izquierda la **Zona de Alta Tensión (`220V` — Fuente y Relé)** y a la derecha la **Zona de Baja Tensión (`5V` — ESP32, sensores y LEDs)**. Esto cumple con las normas de seguridad eléctrica (evita que un cable de 220V suelto toque el ESP32) y aísla el calor de la fuente.
4. **Esquineros en "L" y Rieles Elevados:** Las placas calzan en soportes en forma de "L" en las 4 esquinas (dejando los laterales libres para conectar cables y borneras sin obstrucciones) y el ESP32 descansa elevado a `5 mm` del piso para no doblar sus pines inferiores.
5. **Rejillas de Ventilación Superior:** La tapa tiene ranuras sobre la zona de la fuente `Hi-Link` y del `ESP32` para disipar el calor por **convección natural** (el aire caliente sube y sale por la tapa).

---

## 5. ¿QUÉ PASA EN LA EXPO CUANDO HAY MUCHA GENTE? (RESPUESTA DE 10)

### A. Qué ocurre físicamente en el aire:
1. **Congestión del espectro de `2.4 GHz`:** Cientos de celulares con Wi-Fi y Bluetooth prendidos buscando redes generan ruido electromagnético en la banda de `2.4 GHz`, aumentando la pérdida de paquetes (*Packet Loss*).
2. **Absorción por cuerpos humanos:** El cuerpo humano es 70% agua y absorbe ondas de `2.4 GHz`, disminuyendo el **`RSSI`** (fuerza de señal).
3. **Colapso de `DHCP` del router escolar:** El router de la escuela se queda sin IPs disponibles o sin ancho de banda.
4. **El Infrarrojo (`IR`) NO sufre interferencia de radio:** Porque es luz a `940 nm` modulada a `38 kHz`.

### B. Las 5 defensas programadas en nuestro proyecto:
1. **Hotspot 4G/5G Propio + *Failover* de Doble Red (`WIFI_SSID` y `WIFI_SSID_2`):** El ESP32 se conecta a nuestro Hotspot privado ubicado a 1 metro en el stand (señal máxima y sin visitantes conectados), y si se cae, salta automáticamente a la red de respaldo.
2. **Paquetes de menos de `100 Bytes`:** La sincronización usa texto plano ultracompacto que pasa en milisegundos aun con redes saturadas.
3. **Cola Persistente con Confirmación (`ACK` en MySQL):** Ningún comando se pierde por un microcorte; queda en la base de datos hasta que el ESP32 confirma con `ir_ejecutado` que ya lo disparó.
4. ***Timeout* y *Backoff* Inteligente (`5000 ms`):** El ESP32 no se bloquea si una petición demora; corta a los 5 segundos y espera antes de reintentar.
5. **Servidor Web Local de Emergencia en el Puerto `80` del ESP32:** Si se cortara internet en todo el barrio, podemos entrar directo a la IP local del ESP32 desde el celular y seguir manejando el aula.

---

## 6. PREGUNTAS "TRAMPA" EXTRA QUE SUELEN HACER LOS PROFESORES

1. ***"¿Por qué usaron un ESP32 y no un Arduino Uno?"***
   * **Respuesta:** *"Porque el Arduino Uno no tiene Wi-Fi integrado, tiene solo `2 KB` de memoria RAM (contra `520 KB` del ESP32) y corre a `16 MHz` (contra los dos núcleos a `240 MHz` del ESP32). Con el ESP32 podemos mantener la conexión HTTP al servidor, levantar un servidor web local en el puerto 80 y procesar tramas infrarrojas largas en simultáneo."*
2. ***"¿Qué tiene adentro el módulo Relé para proteger al ESP32 de un chispazo de 220V?"***
   * **Respuesta:** *"Tiene dos protecciones: un **Optoacoplador** (que transmite la señal internamente mediante un LED y un fototransistor sin contacto eléctrico directo entre la entrada de 5V y el relé) y un **Diodo de Rueda Libre (Flyback)** en paralelo con la bobina, que absorbe el pico de tensión inversa que genera la bobina magnética cuando se apaga."*
3. ***"¿Por qué el Proyector Epson necesita dos pulsos para apagarse y el Aire Acondicionado uno solo?"***
   * **Respuesta:** *"Porque los proyectores Epson tienen una protección por seguridad de lámpara: el primer pulso `0xC1AA09F6` solo abre el cartel en pantalla que pregunta '¿Desea apagar el proyector?'. Nuestro firmware detecta cuando la orden es `apagar` del proyector y envía automáticamente el primer pulso, espera `2000 milisegundos` (`2 segundos`) y envía el segundo pulso de confirmación."*
4. ***"¿Cómo sabe el panel web en tiempo real si el ESP32 está `ONLINE` u `OFFLINE`?"***
   * **Respuesta:** *"Cada vez que el ESP32 se sincroniza (cada 1,5 segundos), el servidor actualiza la columna `ultima_conexion = NOW()` en la tabla `aulas` de MySQL, junto con su IP y su señal `RSSI`. Cuando abrimos el panel web, la API calcula con `TIMESTAMPDIFF` cuántos segundos pasaron desde el último latido (*Heartbeat*): si pasaron menos de 15 segundos, muestra `AULA ONLINE` en verde; si el ESP32 se desenchufa, pasa automáticamente a `AULA OFFLINE` en rojo."*

