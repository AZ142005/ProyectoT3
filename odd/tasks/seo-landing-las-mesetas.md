# SEO local — Landing pública del Conjunto Las Mesetas de Morón

Fecha: 2026-10-18
Estado: en progreso

## Objetivo

Posicionar el conjunto para búsquedas de marca en Venezuela (`conjunto las mesetas`, `condominio las mesetas`, `las mesetas de morón`) y dar a los residentes una entrada clara al portal de pagos, separada del login.

## Contexto verificado

- Entidad oficial: **Conjunto Residencial "Las Mesetas de Morón"**.
- Dirección: Av. Principal de Morón, Urb. Las Mesetas, Parroquia Mercedes Díaz, Valera, Estado Trujillo, Venezuela.
- Teléfono: 0271-2259170. RIF: J-400577870. Correo: pagosjdclm@gmail.com.
- Redes oficiales confirmadas por el usuario: Instagram `@conjunto.lasmesetas` y Facebook `lasmesetasdmoron`.
- El SERP de marca es ambiguo: compite con un Condominio Las Mesetas en Costa Rica, una cooperativa homónima y cuentas sociales; no hay sitio oficial indexado.
- Hoy `/` renderiza el login. No hay meta description, canonical, Open Graph, JSON-LD, `robots.txt` ni `sitemap.xml`.
- `/auth/register` y `/pago-directo` exponen inventario de edificios/unidades: no deben indexarse.

## Alcance

- Landing pública en `/` (controlador + vista + ruta).
- Metadatos SEO reutilizables en `app/views/layouts/header.php` (description, robots, canonical, Open Graph, Twitter, JSON-LD, `lang="es-VE"`).
- Helper `app_url()` y `APP_URL` en `.env.example` para URLs absolutas.
- `robots.txt` y `sitemap.xml` dinámicos (funcionan en local, InfinityFree y dominio propio).
- `noindex` y títulos de marca en login, registro, 2FA, pago directo y confirmaciones.
- Tests estáticos en `tests/SeoLandingTest.php`.

## Fuera de alcance

- Compilar Tailwind ni eliminar el CDN (mejora de rendimiento posterior).
- Crear la ficha de Google Business (acción externa del usuario).
- Cambiar el dominio o el hosting.
- Abrir PR o desplegar: la entrega queda para decisión del usuario.

## Ruta y TDD

- Ruta: delegada a un escritor (2+ archivos no triviales).
- TDD: off — runner custom `php tests/run.php`; se agregan tests estáticos junto al comportamiento.
- Presupuesto: ~550 líneas autoradas. Sin PR en esta sesión; estrategia de entrega `ask-on-risk` queda diferida hasta que el usuario decida publicar.

## Checklist

- [x] T1 Landing pública en `/` con H1, NAP, CTA a pago directo/login y preguntas frecuentes.
- [x] T2 Metadatos reutilizables en `header.php` + helper `app_url()` + `APP_URL` documentada.
- [x] T3 `robots.txt` y `sitemap.xml` dinámicos; páginas utilitarias con `noindex`.
- [x] T4 Tests estáticos de landing, metadatos, rutas y noindex.
- [x] T5 Verificación: `php -l`, `php tests/run.php --filter=SeoLanding`, smoke HTTP de `/`, `/robots.txt`, `/sitemap.xml`, `/auth/login`.
- [x] T6 Commits por unidad de trabajo en rama `feat/seo-landing-las-mesetas`.

## Evidencia

- Unidad 1 (commit `1d2a2b9`): landing en `/`, `HomeController`, `app_url()`, `APP_URL` documentada y tests de landing/helper. 518 líneas autoradas; por encima del heurístico de 400, pero es una unidad coherente (vista de 269 líneas). No se recortó contenido para ajustar el número.
- Unidad 2: metadatos en `header.php`, `SeoController`, rutas `/robots.txt` y `/sitemap.xml`, `noindex` en login/registro/2FA/pago directo y tests de metadatos/rastreo.
- `php -l` sin errores en los 9 archivos PHP tocados.
- `php tests/run.php --filter=SeoLanding`: 3 tests, 10 assertions, 0 fallos.
- `php tests/run.php --filter=SeoMetadata`: pendiente de ejecutar en la unidad 2.
- Smoke HTTP con `php -S 127.0.0.1:8099 -t public public/index.php`: `/` 200 con H1, canonical, `index,follow`; `/robots.txt` 200 con `Sitemap:`; `/sitemap.xml` 200 con un solo `<url>`; `/auth/login` 200 con `noindex,follow`.
- Suite completa: aborta en `SecurityTest` como en la línea base documentada; los tests focalizados pasan.
- Entrega: sin PR en esta sesión. Si se publica, la estrategia `ask-on-risk` queda pendiente por el presupuesto de revisión.
