# Rutina de ejercicios (Oksigenia Jepyso)

[Read in English](README.md)

Una actividad para Moodle. El docente pone una rutina de ejercicios; cada estudiante la hace en el teléfono o en el
ordenador, siguiendo a una figura en 3D que lleva el ritmo, el lado y las series que dice la rutina. Al acabar, apunta
las repeticiones que hizo y la molestia que notó (de 0 a 10). Cada sesión se guarda en Moodle, cuenta para la
finalización y la calificación, y el docente ve el seguimiento de cada estudiante.

Está hecha para cualquier centro educativo o de formación: clases de Educación Física y escuelas de fisioterapia,
entrenamiento o ciencias del deporte.

## Requisitos

- Moodle 4.5 a 5.3.
- Ningún servicio externo: el reproductor lo sirve tu propio sitio y nada sale de él.

## Instalación

1. Copia el plugin en `mod/oksigeniajepyso` (desde Moodle 5.1, `public/mod/oksigeniajepyso`) o instala el ZIP desde
   *Administración del sitio > Extensiones > Instalar complementos*.
2. Entra en *Administración del sitio > Notificaciones* o ejecuta `admin/cli/upgrade.php`.

## Cómo funciona

- **La rutina.** En los ajustes de la actividad, pega el enlace de una rutina hecha con Jepyso
  (`https://app.jepyso.com/#r=…`) o su código, o pulsa «Armar la rutina» para hacer una: vuelve sola al formulario.
  Pon cuántas sesiones debe hacer cada estudiante.
- **Las sesiones.** «Empezar» abre el reproductor (`reproductor/`, dentro del plugin). Sigue el ritmo, el lado y las
  series de cada ejercicio; al final, el estudiante apunta lo que hizo. El reproductor guarda con los servicios web
  `mod_oksigeniajepyso_save_sessions` y `mod_oksigeniajepyso_get_sessions`.
- **Finalización y calificación.** Condición «Hacer las sesiones previstas». Con una calificación en puntos y un número
  de sesiones, la nota es la calificación máxima por la parte de las sesiones hechas (nunca más que la máxima); si no,
  califica el docente en el libro de calificaciones.
- **Seguimiento.** El docente ve, de cada estudiante, las sesiones hechas, la última, los días con más molestia de la
  aceptable, los ejercicios que le cuestan o que no ha podido hacer y las notas, y puede abrir los días de cada uno.
  Respeta los grupos separados y visibles. El docente puede probar la rutina; lo que haga no se guarda.
- **Los ejercicios.** El plugin trae una muestra gratuita de la biblioteca de ejercicios. La biblioteca completa la
  sirve Oksigenia con una clave por sitio, de pago; esta versión aún no la incluye. Al docente se le muestran los
  ejercicios de una rutina que no están en la muestra.

## En la app de Moodle

La actividad funciona en la app oficial de Moodle, sin configurar nada. Su página muestra la descripción, los
ejercicios con su pauta y el progreso; «Empezar» abre el reproductor en el navegador de la app, con la sesión ya
iniciada, y las sesiones se guardan igual que en la web.

## Privacidad

Las sesiones se guardan en el sitio (`oksigeniajepyso_session`), declaradas en la API de privacidad, que las exporta y
las borra. La copia de seguridad y la restauración las incluyen con los datos de usuario; el reinicio del curso puede
borrarlas.

## Licencia

GNU GPL v3 o posterior (`LICENSE`). Copyright 2026 Oksigenia SL. El reproductor de `reproductor/` es la app web de
Jepyso (https://github.com/OksigeniaSL/jepyso), GPL v3 o posterior, con three.js (MIT); sus figuras en 3D son
contenido con sus propias condiciones (ver `thirdpartylibs.xml`).
