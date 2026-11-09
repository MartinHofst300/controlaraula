// ============================================================================
// PROYECTO: NODO DE CONTROL INTELIGENTE DE AULA (IoT ESP32 + IR + RELE 220V)
// ARCHIVO:  carcasa_triangular.scad (Diseño Paramétrico 3D en OpenSCAD)
// ============================================================================
// COMPONENTES REALES ALOJADOS:
// 1. ESP32 NodeMCU-32S Lua WiFi IoT BT (30 pines, CH340C, USB-C): 52 x 29 x 15 mm
// 2. Módulo Relé 1 Canal 5V Optoacoplado (10A 250VAC):            50 x 26 x 18.5 mm
// 3. Fuente Switching Aislada Hi-Link HLK-5M05 (220V a 5V 1A):    38 x 23 x 18 mm
// 4. Receptor Infrarrojo KY-022 (38 kHz):                         22 x 15 x 10 mm
// 5. Arnés soldado: 2x LEDs Emisores IR 5mm en serie + BC337 + Resistencias
//    + Cables Dupont de 20 cm.
// ============================================================================

$fn = 64; // Resolución de curvas para exportación STL suave

// --- SELECTOR DE PIEZA A EXPORTAR ---
// Opciones: "todo", "base", "tapa", "rotulas"
pieza_a_mostrar = "todo";

// --- MEDIDAS GENERALES DE LA CARCASA TRIANGULAR (Prisma Rectángulo 90°) ---
cateto_x        = 160.0; // Pared inferior (Baja Tensión / USB-C)
cateto_y        = 160.0; // Pared izquierda (Alta Tensión 220V)
altura_interna  = 50.0;  // 50 mm libres para cables Dupont de 20cm sin aplastar
espesor_pared   = 2.8;   // Paredes sólidas (7 perímetros con boquilla 0.4mm)
espesor_piso    = 3.0;   // Base firme con ranuras de colgado
espesor_tapa    = 3.0;   // Tapa superior atornillada con ventilación
altura_total    = espesor_piso + altura_interna;

// --- MEDIDAS REALES DE LOS COMPONENTES (+0.8mm de tolerancia de impresión) ---
// 1. ESP32 USB-C (52 x 29 x 15 mm)
esp32_largo     = 52.8;
esp32_ancho     = 29.8;
esp32_z_cuna    = 4.0;   // Elevación para que los pines inferiores no toquen el piso

// 2. Módulo Relé 1 Canal (50 x 26 x 18.5 mm)
rele_largo      = 50.8;
rele_ancho      = 26.8;
rele_z_cuna     = 4.0;

// 3. Fuente Hi-Link HLK-5M05 (38 x 23 x 18 mm)
hilink_largo    = 38.8;
hilink_ancho    = 23.8;
hilink_z_cuna   = 10.0;  // Elevada 10mm porque tiene pines soldados por debajo (+Vo/-Vo y AC)

// 4. Rótulas esféricas orientables para los 2 LEDs IR de 5mm
diam_esfera_rotula = 16.0;
diam_led_ir        = 5.2;
ranura_cable_led   = 2.8; // Ranura lateral en "C" para meter el LED ya soldado sin desoldar

// ============================================================================
// MÓDULOS DE GEOMETRÍA BASE
// ============================================================================

module perfil_triangulo(lado_x, lado_y) {
    polygon(points = [
        [0, 0],
        [lado_x, 0],
        [0, lado_y]
    ]);
}

// ============================================================================
// PIEZA 1: BASE DE LA CARCASA TRIANGULAR
// ============================================================================
module base_carcasa() {
    difference() {
        union() {
            // 1. Casco exterior triangular hueco
            difference() {
                linear_extrude(height = altura_total)
                    perfil_triangulo(cateto_x, cateto_y);

                // Vaciado interior (dejando paredes de 2.8 mm y piso de 3.0 mm)
                translate([espesor_pared, espesor_pared, espesor_piso])
                    linear_extrude(height = altura_interna + 1)
                        polygon(points = [
                            [0, 0],
                            [cateto_x - espesor_pared * 2.414, 0],
                            [0, cateto_y - espesor_pared * 2.414]
                        ]);
            }

            // 2. Torretas en los 3 vértices para tornillos M3 de cierre de tapa
            for (pos = [[9, 9], [142, 8], [8, 142]]) {
                translate([pos[0], pos[1], espesor_piso])
                    difference() {
                        cylinder(d = 9.0, h = altura_interna);
                        translate([0, 0, altura_interna - 18])
                            cylinder(d = 2.7, h = 19); // Para roscar M3 directo
                    }
            }

            // 3. Tabique Dieléctrico Interno de Seguridad (Separa 220V AC de 5V DC)
            // Todo lo que está a la izquierda (X < 36mm) es Zona 220V AC (Hi-Link + Relé)
            // Todo lo que está a la derecha (X > 38.4mm) es Zona 5V DC (ESP32 + IR)
            translate([36.0, espesor_pared, espesor_piso])
                difference() {
                    cube([2.4, 118.0, 38.0]);
                    // Pasacables superior para los cables de 5V (+Vo/-Vo de Hi-Link)
                    translate([-1, 28, 26]) cube([5, 10, 14]);
                    // Pasacables superior para señal del Relé (VCC/GND/IN)
                    translate([-1, 82, 26]) cube([5, 12, 14]);
                }

            // 4. Cuna Elevada para Fuente Hi-Link HLK-5M05 (Zona 220V: X=4..32, Y=16..59)
            // Incluye túnel inferior de 10mm para las patas soldadas +Vo/-Vo y AC
            // y tabique aislante inferior entre el lado AC y el lado 5V.
            translate([4.5, 16.0, espesor_piso]) {
                // Paredes laterales de la cuna
                cube([hilink_ancho + 4, 2.0, hilink_z_cuna + 8]);
                translate([0, hilink_largo + 2, 0])
                    cube([hilink_ancho + 4, 2.0, hilink_z_cuna + 8]);
                // Escalones de apoyo a 10mm del piso para que no toquen los pines soldados
                translate([0, 0, 0]) cube([4.0, hilink_largo + 4, hilink_z_cuna]);
                translate([hilink_ancho, 0, 0]) cube([4.0, hilink_largo + 4, hilink_z_cuna]);
                // Barrera dieléctrica central debajo de la Hi-Link (separa pines AC de +Vo/-Vo)
                translate([(hilink_ancho + 4)/2 - 1.2, 0, 0])
                    cube([2.4, hilink_largo + 4, hilink_z_cuna]);
            }

            // 5. Cuna para Módulo Relé 1 Canal (Zona 220V: X=4..35, Y=64..119)
            translate([4.0, 64.0, espesor_piso]) {
                difference() {
                    cube([rele_ancho + 4, rele_largo + 4, rele_z_cuna + 6]);
                    translate([2.0, 2.0, rele_z_cuna])
                        cube([rele_ancho, rele_largo, 10]);
                    translate([4.0, 4.0, -1])
                        cube([rele_ancho - 4, rele_largo - 4, rele_z_cuna + 2]);
                }
            }

            // 6. Cuna para ESP32 USB-C (Zona 5V: X=48..82, Y=2.8..58)
            // El puerto USB-C mira directo hacia la pared Y=0 en X=64.9mm
            translate([48.0, espesor_pared, espesor_piso]) {
                difference() {
                    cube([esp32_ancho + 4, esp32_largo + 2, esp32_z_cuna + 6]);
                    translate([2.0, 0, esp32_z_cuna])
                        cube([esp32_ancho, esp32_largo, 10]);
                    translate([4.5, 3.0, -1])
                        cube([esp32_ancho - 5, esp32_largo - 6, esp32_z_cuna + 2]);
                }
            }

            // 7. Bandeja Organizadora para Arnés Soldado (BC337 + Resistencias + Cables 20cm)
            translate([88.0, 8.0, espesor_piso]) {
                difference() {
                    cube([38.0, 24.0, 12.0]);
                    translate([2.0, 2.0, 0]) cube([34.0, 20.0, 14.0]);
                    // Ranuras de entrada/salida de cables del arnés
                    translate([-1, 6, 4]) cube([42, 12, 10]);
                }
            }

            // 8. Cuna vertical para el Receptor IR KY-022 (Centro de la Hipotenusa)
            translate([80.0, 80.0, espesor_piso])
                rotate([0, 0, -45])
                    translate([-11, -10, 0]) {
                        difference() {
                            cube([22.0, 10.0, 22.0]);
                            translate([2.0, 2.0, 4.0]) cube([18.0, 8.0, 20.0]);
                        }
                    }

            // 9. Alojamientos Esféricos Reforzados (Sockets) en la Hipotenusa para las 2 Rótulas IR
            for (pos_led = [[56.0, 104.0], [104.0, 56.0]]) {
                translate([pos_led[0], pos_led[1], espesor_piso + 22.0])
                    rotate([0, 0, -45])
                        sphere(d = diam_esfera_rotula + 5.0);
            }

            // 10. Puentes Internos Sujetacables (Antitirón para precintos en cables de 220V)
            for (y_cable = [35.0, 90.0]) {
                translate([espesor_pared, y_cable - 6, espesor_piso])
                    difference() {
                        cube([6.0, 12.0, 10.0]);
                        translate([2.0, -1, 3.0]) cube([4.5, 14.0, 4.5]);
                    }
            }
        }

        // ====================================================================
        // CORTES, AGUJEROS PASACABLES Y VENTANAS
        // ====================================================================

        // A. Agujero para Conector USB Tipo-C del ESP32 (Pared Y=0, X=64.9mm)
        // Medida holgada de 13 x 7.5 mm para que entre el plástico de cualquier cable USB-C
        translate([48.0 + 2.0 + esp32_ancho/2 - 6.5, -1.0, espesor_piso + esp32_z_cuna - 1.5])
            cube([13.0, espesor_pared + 4.0, 7.5]);

        // B. Agujero Entrada 220V AC Línea (Fase + Neutro) hacia Hi-Link (Pared X=0, Y=35mm)
        translate([-1.0, 35.0, espesor_piso + 14.0])
            rotate([0, 90, 0])
                cylinder(d = 7.5, h = espesor_pared + 3.0);

        // C1. Boca Lateral para Cablecanal Estándar de Pared (20 x 12 mm) en Pared X=0, Y=82..102mm
        //     Permite entrar con el cablecanal blanco de colegio (Fase, Neutro y Retorno de Luz de Pared)
        translate([-1.0, 82.0, espesor_piso + 9.0])
            cube([espesor_pared + 3.0, 20.0, 12.0]);

        // C2. Ventana Posterior/Inferior para Caja de Luz Embutida en Pared (X=8..28, Y=62..78)
        //     Permite pasar los cables de la tecla de luz directo desde la pared sin verse por afuera
        translate([8.0, 62.0, -1.0])
            cube([20.0, 16.0, espesor_piso + 2.0]);

        // C3. Ojales de Amure a Pared en ambos Catetos (para tornillo y tarugo del 6 en esquina/muro)
        translate([112.0, -1.0, 32.0])
            cube([5.0, espesor_pared + 3.0, 8.0]);
        translate([-1.0, 122.0, 32.0])
            cube([espesor_pared + 3.0, 5.0, 8.0]);

        // D. Ventana Frontal para el Receptor IR KY-022 (Centro Hipotenusa: X=80, Y=80)
        translate([80.0, 80.0, espesor_piso + 16.0])
            rotate([0, 0, -45])
                rotate([-90, 0, 0])
                    cylinder(d = 8.5, h = 20.0, center = true);

        // E. Cavidades Esféricas + Ranuras Pasacable para las 2 Rótulas de LEDs IR
        //    - Rótula 1 (Izquierda: Aire Acondicionado) en (56, 104)
        //    - Rótula 2 (Derecha: Proyector) en (104, 56)
        for (pos_led = [[56.0, 104.0], [104.0, 56.0]]) {
            translate([pos_led[0], pos_led[1], espesor_piso + 22.0])
                rotate([0, 0, -45]) {
                    // Cavidad esférica con +0.4mm de holgura para giro firme por fricción
                    sphere(d = diam_esfera_rotula + 0.4);
                    // Cono frontal y trasero de apertura (±40 grados de orientación libre)
                    rotate([-90, 0, 0])
                        cylinder(d1 = 13.5, d2 = 22.0, h = 12.0);
                    rotate([90, 0, 0])
                        cylinder(d1 = 13.5, d2 = 22.0, h = 12.0);
                    // Ranura superior pasacable de 3mm (permite sacar el LED con su cable
                    // como "antenita externa" si en algún aula hace falta!)
                    translate([-1.6, -12.0, 0])
                        cube([3.2, 24.0, 35.0]);
                }
        }

        // F. Rejillas Laterales Inferiores de Entrada de Aire Fresco (Zona 220V)
        for (i = [0 : 4]) {
            translate([-1.0, 20.0 + i * 6.0, espesor_piso + 28.0])
                cube([espesor_pared + 2.0, 2.2, 14.0]);
        }

        // G. 2 Agujeros Traseros tipo "Ojo de Cerradura" (Keyholes) en el piso y paredes
        //    para colgar en esquina o apoyar en mesa
        for (pos_kh = [[35.0, 18.0], [18.0, 110.0]]) {
            translate([pos_kh[0], pos_kh[1], -1.0]) {
                cylinder(d = 8.0, h = espesor_piso + 2.0);
                translate([-2.2, 0, 0]) cube([4.4, 10.0, espesor_piso + 2.0]);
            }
        }
    }
}

// ============================================================================
// PIEZA 2: TAPA SUPERIOR TRIANGULAR CON VENTILACIÓN TÉRMICA
// ============================================================================
module tapa_carcasa() {
    difference() {
        union() {
            // Placa triangular principal
            linear_extrude(height = espesor_tapa)
                perfil_triangulo(cateto_x, cateto_y);

            // Labio interior de encastre y alineación (-0.4mm de tolerancia)
            translate([espesor_pared + 0.4, espesor_pared + 0.4, -2.0])
                difference() {
                    linear_extrude(height = 2.0)
                        polygon(points = [
                            [0, 0],
                            [cateto_x - (espesor_pared + 0.4) * 2.414, 0],
                            [0, cateto_y - (espesor_pared + 0.4) * 2.414]
                        ]);
                    translate([2.0, 2.0, -1])
                        linear_extrude(height = 4.0)
                            polygon(points = [
                                [0, 0],
                                [cateto_x - (espesor_pared + 2.4) * 2.414, 0],
                                [0, cateto_y - (espesor_pared + 2.4) * 2.414]
                            ]);
                    // Recortes para no chocar con las 3 torretas de esquina
                    for (pos = [[9, 9], [142, 8], [8, 142]]) {
                        translate([pos[0] - espesor_pared, pos[1] - espesor_pared, -1])
                            cylinder(d = 12.0, h = 5.0);
                    }
                }
        }

        // 1. Agujeros pasantes y fresados para los 3 tornillos M3
        for (pos = [[9, 9], [142, 8], [8, 142]]) {
            translate([pos[0], pos[1], -3.0]) {
                cylinder(d = 3.4, h = espesor_tapa + 6.0);
                translate([0, 0, espesor_tapa + 1.2])
                    cylinder(d = 6.5, h = 3.0);
            }
        }

        // 2. Rejillas de Ventilación sobre la Fuente Hi-Link y el Relé (Convección Térmica)
        for (y_slot = [20 : 6 : 110]) {
            translate([8.0, y_slot, -1.0])
                cube([22.0, 2.4, espesor_tapa + 2.0]);
        }

        // 3. Rejillas de Ventilación sobre el ESP32
        for (y_slot = [12 : 6 : 48]) {
            translate([54.0, y_slot, -1.0])
                cube([20.0, 2.4, espesor_tapa + 2.0]);
        }
    }
}

// ============================================================================
// PIEZA 3: RÓTULAS ESFÉRICAS ABIERTAS (SLOTTED BALL-JOINTS) PARA LEDS IR 5MM
// ============================================================================
// Diseño especial con ranura lateral en "C" de 2.8 mm:
// Permite pasar el cable ya soldado de costado y encastrar el LED de 5mm
// en el centro de la esfera SIN TENER QUE DESOLDAR NADA.
module rotula_led_ir() {
    difference() {
        // Esfera exterior de 16 mm con base plana mínima para imprimir sin soportes
        intersection() {
            sphere(d = diam_esfera_rotula);
            cube([diam_esfera_rotula, diam_esfera_rotula, diam_esfera_rotula - 2.0], center = true);
        }

        // Agujero central de 5.2 mm para el cuerpo del LED IR de 5mm
        cylinder(d = diam_led_ir, h = diam_esfera_rotula + 2.0, center = true);

        // Escalón trasero de 6.0 mm para el reborde (flange) del LED de 5mm
        translate([0, 0, -diam_esfera_rotula/2 - 1.0])
            cylinder(d = 6.0, h = diam_esfera_rotula/2 + 1.0);

        // RANURA LATERAL PASACABLE EN "C" (2.8 mm de ancho):
        // Por acá entra el cable ya soldado sin desoldar el LED!
        translate([0, -ranura_cable_led/2, -diam_esfera_rotula])
            cube([diam_esfera_rotula, ranura_cable_led, diam_esfera_rotula * 2]);
    }
}

// ============================================================================
// RENDERIZADO SEGÚN SELECCIÓN
// ============================================================================
if (pieza_a_mostrar == "todo") {
    color("#2563eb") base_carcasa();
    translate([0, 0, altura_total + 15])
        color("#60a5fa", 0.85) tapa_carcasa();
    for (pos_led = [[56.0, 104.0], [104.0, 56.0]]) {
        translate([pos_led[0], pos_led[1], espesor_piso + 22.0])
            rotate([90, 0, -45])
                color("#f59e0b") rotula_led_ir();
    }
} else if (pieza_a_mostrar == "base") {
    base_carcasa();
} else if (pieza_a_mostrar == "tapa") {
    rotate([180, 0, 0]) tapa_carcasa(); // Invertida para imprimir plana sin soportes
} else if (pieza_a_mostrar == "rotulas") {
    translate([-12, 0, (diam_esfera_rotula - 2.0)/2]) rotula_led_ir();
    translate([ 12, 0, (diam_esfera_rotula - 2.0)/2]) rotula_led_ir();
}

