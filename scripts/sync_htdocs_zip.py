#!/usr/bin/env python3
import os
import shutil
import zipfile
import tempfile
import sys

def main():
    repo_root = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
    source_zip = os.path.join(repo_root, 'database', 'htdocs.zip')
    uploaded_zip = r'C:/Users/Lenovo IdeaPad 3/.gemini/antigravity/brain/416a1b6b-f935-4af9-a136-5c5beaae82a3/.user_uploaded/media_1793245620726_39a60739.zip'
    dest_zip_root = os.path.join(repo_root, 'htdocs.zip')
    dest_zip_db = os.path.join(repo_root, 'database', 'htdocs.zip')

    if not os.path.exists(source_zip) and os.path.exists(uploaded_zip):
        source_zip = uploaded_zip

    print(f"1. Usando archivo base: {source_zip}")
    temp_dir = tempfile.mkdtemp(prefix="htdocs_sync_")
    staging_htdocs = os.path.join(temp_dir, "htdocs")
    os.makedirs(staging_htdocs, exist_ok=True)

    special_files = [
        "htdocs/.htaccess",
        "htdocs/.env",
        "htdocs/files for your website should be uploaded here!",
        "htdocs/logs/.gitkeep",
        "htdocs/storage/backups/.gitkeep",
        "htdocs/storage/cache/.gitkeep",
        "htdocs/storage/logs/.gitkeep",
        "htdocs/uploads/comprobantes/.gitkeep",
        "htdocs/uploads/soportes/.gitkeep",
    ]

    print("2. Extrayendo archivos especiales existentes de htdocs.zip...")
    if os.path.exists(source_zip):
        with zipfile.ZipFile(source_zip, 'r') as z:
            for spec in special_files:
                try:
                    info = z.getinfo(spec)
                    dest_file = os.path.join(temp_dir, spec.replace('/', os.sep))
                    os.makedirs(os.path.dirname(dest_file), exist_ok=True)
                    with open(dest_file, 'wb') as f:
                        f.write(z.read(info))
                    print(f"   [OK] Preservado: {spec}")
                except KeyError:
                    print(f"   [--] No encontrado en zip original: {spec}")

    # Asegurar CONCILIACION_VENTANA_LOTES en htdocs/.env
    env_deploy = os.path.join(staging_htdocs, ".env")
    if os.path.exists(env_deploy):
        with open(env_deploy, 'r', encoding='utf-8', errors='ignore') as f:
            env_content = f.read()
        if "CONCILIACION_VENTANA_LOTES" not in env_content:
            env_content += "\n# Ventana de conciliacion: lotes activos por banco\nCONCILIACION_VENTANA_LOTES=3\n"
            with open(env_deploy, 'w', encoding='utf-8', newline='\n') as f:
                f.write(env_content)
            print("   [OK] Agregado CONCILIACION_VENTANA_LOTES=3 a htdocs/.env")

    print("\n3. Copiando archivos actualizados desde el repositorio...")

    def copy_filtered_dir(src_name, dest_sub):
        src_path = os.path.join(repo_root, src_name)
        target_path = os.path.join(staging_htdocs, dest_sub)
        copied = 0
        for root, dirs, files in os.walk(src_path):
            for file in files:
                full_path = os.path.join(root, file)
                rel_path = os.path.relpath(full_path, src_path)

                # Exclusiones
                if src_name == 'app' and 'logs' in rel_path.split(os.sep) and file.endswith('.log'):
                    continue
                if src_name == 'public' and 'uploads' in rel_path.split(os.sep):
                    continue
                if '__pycache__' in rel_path or file.endswith('.pyc'):
                    continue

                if src_name == 'scripts' and file in {
                    'propietarios_filas.json',
                    'condominos_filas.json',
                    'cartelera_filas.json',
                    'importar_propietarios_por_unidad.php',
                    'ejecutar_carga_cartelera.php',
                    'revertir_pago_prueba_1_25.php',
                    'eliminar_pago_prueba_1_01.php',
                }:
                    continue

                dest_file = os.path.join(target_path, rel_path)
                os.makedirs(os.path.dirname(dest_file), exist_ok=True)
                shutil.copy2(full_path, dest_file)
                copied += 1
        print(f"   [OK] {src_name}/ -> htdocs/{dest_sub}/ ({copied} archivos)")

    copy_filtered_dir("app", "app")
    copy_filtered_dir("public", "public")
    copy_filtered_dir("vendor", "vendor")
    copy_filtered_dir("scripts", "scripts")

    # Copiar .env.example
    env_example_src = os.path.join(repo_root, ".env.example")
    if os.path.exists(env_example_src):
        shutil.copy2(env_example_src, os.path.join(staging_htdocs, ".env.example"))

    print("\n4. Normalizando saltos de línea a Unix LF (\\n) en archivos de texto...")
    text_extensions = {".php", ".sql", ".js", ".css", ".json", ".htaccess", ".env", ".example", ".svg", ".py", ".sh", ".txt", ".md"}
    normalized = 0
    for root, dirs, files in os.walk(staging_htdocs):
        for file in files:
            ext = os.path.splitext(file)[1].lower()
            if ext in text_extensions or file in {".htaccess", ".env", ".env.example"}:
                fp = os.path.join(root, file)
                try:
                    with open(fp, 'rb') as f:
                        b = f.read()
                    if b'\r\n' in b:
                        b = b.replace(b'\r\n', b'\n')
                        with open(fp, 'wb') as f:
                            f.write(b)
                        normalized += 1
                except Exception:
                    pass
    print(f"   [OK] Archivos normalizados a LF: {normalized}")

    print("\n5. Comprimiendo paquete htdocs.zip...")
    out_temp_zip = os.path.join(temp_dir, "htdocs_new.zip")
    with zipfile.ZipFile(out_temp_zip, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for root, dirs, files in os.walk(temp_dir):
            for file in files:
                if file == "htdocs_new.zip":
                    continue
                fp = os.path.join(root, file)
                arcname = os.path.relpath(fp, temp_dir).replace('\\', '/')
                z.write(fp, arcname)

    size = os.path.getsize(out_temp_zip)
    mb = round(size / (1024 * 1024), 2)
    print(f"   [OK] Zip generado: {size} bytes ({mb} MB)")

    print("\n6. Validando integridad y estructura del nuevo zip...")
    with zipfile.ZipFile(out_temp_zip, 'r') as z:
        names = set(z.namelist())

    checks = [
        "htdocs/.htaccess",
        "htdocs/.env",
        "htdocs/.env.example",
        "htdocs/public/index.php",
        "htdocs/public/docs/manual-usuario-residente.pdf",
        "htdocs/app/config/config.php",
        "htdocs/app/Controllers/PagoDirectoController.php",
        "htdocs/app/Models/UnidadesModel.php",
        "htdocs/app/views/pago_directo/index.php",
        "htdocs/app/views/home/index.php",
        "htdocs/app/views/residente/dashboard.php",
        "htdocs/vendor/autoload.php",
        "htdocs/files for your website should be uploaded here!"
    ]

    all_passed = True
    for chk in checks:
        if chk in names:
            print(f"   [OK] Verificado: {chk}")
        else:
            print(f"   [FAIL] No se encuentra: {chk}")
            all_passed = False

    if not all_passed:
        print("ERROR: Fallo la validacion del zip. No se reemplazara el archivo.")
        sys.exit(1)

    print("\n7. Distribuyendo zip actualizado a las ubicaciones destino...")
    # 1. database/htdocs.zip
    shutil.copy2(out_temp_zip, dest_zip_db)
    print(f"   [OK] Guardado en: {dest_zip_db}")

    # 2. htdocs.zip en repo root
    shutil.copy2(out_temp_zip, dest_zip_root)
    print(f"   [OK] Guardado en: {dest_zip_root}")

    # 3. uploaded_zip si existe la ruta
    if os.path.exists(os.path.dirname(uploaded_zip)):
        try:
            shutil.copy2(out_temp_zip, uploaded_zip)
            print(f"   [OK] Actualizado archivo subido: {uploaded_zip}")
        except Exception as e:
            print(f"   [--] No se pudo sobrescribir uploaded_zip: {e}")

    # Limpiar temp
    shutil.rmtree(temp_dir, ignore_errors=True)
    print(f"\nSUCCESS: htdocs.zip actualizado y validado exitosamente con {len(names)} entradas.")

if __name__ == '__main__':
    main()
