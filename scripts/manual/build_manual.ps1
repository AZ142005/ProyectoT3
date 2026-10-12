# =============================================================================
# build_manual.ps1
# Builds "Manual de Usuario Final" (DOCX + PDF) from contenido_manual.json
# using Word COM automation. Idempotent: reruns regenerate everything.
#
# Requirements: Windows PowerShell 5.1 + Microsoft Word installed.
# Run:  powershell -ExecutionPolicy Bypass -File scripts\manual\build_manual.ps1
# =============================================================================

$ErrorActionPreference = 'Stop'

# -----------------------------------------------------------------------------
# BGR color helper: Word Font.Color expects BGR = r + g*256 + b*65536
# -----------------------------------------------------------------------------
function Get-BgrColor([int]$r, [int]$g, [int]$b) {
    return [int]($r + ($g * 256) + ($b * 65536))
}

$COLOR_H1      = Get-BgrColor 30 86 49       # #1E5631 institutional green
$COLOR_H3      = Get-BgrColor 51 65 65       # #334155 dark slate
$COLOR_GRAY    = Get-BgrColor 100 116 139    # #64748B caption gray
$COLOR_RED     = Get-BgrColor 185 28 28      # #B91C1C dark red (pending caption)
$COLOR_TIP     = Get-BgrColor 232 245 233    # #E8F5E9 light green note
$COLOR_INFO    = Get-BgrColor 227 242 253    # #E3F2FD light blue note
$COLOR_WARN    = Get-BgrColor 255 243 224    # #FFF3E0 light orange note
$COLOR_YELLOW  = Get-BgrColor 255 249 196    # #FFF9C4 pending placeholder box
$COLOR_HEADER  = Get-BgrColor 241 245 249    # #F1F5F9 table header

# -----------------------------------------------------------------------------
# Paths (resolve repo root from this script location: <repo>\scripts\manual)
# -----------------------------------------------------------------------------
$scriptsDir = $PSScriptRoot
if ([string]::IsNullOrEmpty($scriptsDir)) {
    # Fallback for embedded execution (scriptblock) where $PSScriptRoot is empty
    $scriptsDir = Join-Path (Get-Location).Path 'scripts\manual'
}
$repoRoot   = Split-Path (Split-Path $scriptsDir -Parent) -Parent   # <repo>
$jsonPath   = Join-Path $scriptsDir 'contenido_manual.json'
$docsDir    = Join-Path $repoRoot 'docs'
$docxAbs    = Join-Path $docsDir 'manual de usuario final.docx'
$pdfAbs     = Join-Path $docsDir 'manual de usuario final.pdf'
$logoAbs    = Join-Path $repoRoot 'public\img\logo_condominio.png'

if (-not (Test-Path -LiteralPath $jsonPath)) { throw "No se encontro el archivo de contenido: $jsonPath" }

# Read JSON preserving accents (explicit UTF-8, never Get-Content default)
$rawJson = [System.IO.File]::ReadAllText($jsonPath, [System.Text.Encoding]::UTF8)
$data    = $rawJson | ConvertFrom-Json
$meta    = $data.meta
$nodos   = $data.nodos

# Delete previous outputs (idempotency)
Remove-Item -LiteralPath $docxAbs -Force -ErrorAction SilentlyContinue
Remove-Item -LiteralPath $pdfAbs  -Force -ErrorAction SilentlyContinue

# -----------------------------------------------------------------------------
# Counters
# -----------------------------------------------------------------------------
$script:figureNum    = 0
$script:placeholderCount = 0
$script:fontRetries  = 0

$figureTotal = @($nodos | Where-Object { $_.type -eq 'image' }).Count

# -----------------------------------------------------------------------------
# Formatting helpers (they operate on $word.Selection passed as $sel)
# -----------------------------------------------------------------------------
function Set-FontSize($fontHolder, $value) {
    # Word declares Font.Size as Single. In this environment, assigning a raw
    # Double intermittently raises InvalidCastException; assign an explicit
    # [single] and retry once before giving up.
    $target = [single]$value
    try {
        $fontHolder.Font.Size = $target
    } catch {
        $script:fontRetries++
        Start-Sleep -Milliseconds 80
        $fontHolder.Font.Size = $target
    }
}

function Reset-BodyFormat($sel) {
    try { $sel.Style = -1 } catch { }                    # wdStyleNormal
    $sel.Font.Name = 'Calibri'
    Set-FontSize $sel 11
    $sel.Font.Bold = 0
    $sel.Font.Italic = 0
    $sel.Font.Underline = 0
    $sel.Font.Color = 0
    $sel.ParagraphFormat.Alignment = 3                   # justified
    $sel.ParagraphFormat.SpaceBefore = 0
    $sel.ParagraphFormat.SpaceAfter = 6
    try {
        $sel.ParagraphFormat.LineSpacingRule = 5         # multiple
        $sel.ParagraphFormat.LineSpacing = 13.8          # ~ 1.15 lines
    } catch { }
}

function Write-Heading($sel, [int]$level, [string]$text) {
    $size  = 16
    $color = $COLOR_H1
    $styleEnum = -2
    $outline = 1
    if ($level -eq 2) { $size = 13; $color = $COLOR_H1; $styleEnum = -3; $outline = 2 }
    if ($level -eq 3) { $size = 11.5; $color = $COLOR_H3; $styleEnum = -4; $outline = 3 }

    try {
        $sel.Style = $styleEnum
    } catch {
        try { $sel.Style = -1 } catch { }
        try { $sel.Paragraphs.Item(1).OutlineLevel = $outline } catch { }
    }
    $sel.Font.Name = 'Calibri'
    Set-FontSize $sel $size
    $sel.Font.Bold = 1
    $sel.Font.Italic = 0
    $sel.Font.Color = $color
    $sel.ParagraphFormat.Alignment = 0                   # left
    $sel.ParagraphFormat.SpaceBefore = 12
    $sel.ParagraphFormat.SpaceAfter = 6
    try { $sel.ParagraphFormat.LineSpacingRule = 0 } catch { }   # single
    $sel.TypeText($text)
    $sel.TypeParagraph()
}

function Write-Paragraph($sel, [string]$text) {
    Reset-BodyFormat $sel
    $sel.TypeText($text)
    $sel.TypeParagraph()
}

function Write-CenteredLine($sel, [string]$text, [double]$size) {
    Reset-BodyFormat $sel
    $sel.ParagraphFormat.Alignment = 1                   # center
    $sel.ParagraphFormat.SpaceAfter = 4
    Set-FontSize $sel $size
    $sel.TypeText($text)
    $sel.TypeParagraph()
}

function Write-Caption($sel, [string]$text) {
    $sel.Font.Name = 'Calibri'
    Set-FontSize $sel 9
    $sel.Font.Bold = 0
    $sel.Font.Italic = 1
    $sel.Font.Underline = 0
    $sel.Font.Color = $COLOR_GRAY
    $sel.ParagraphFormat.Alignment = 1                   # center
    $sel.ParagraphFormat.SpaceBefore = 4
    $sel.ParagraphFormat.SpaceAfter = 10
    $sel.TypeText($text)
    $sel.TypeParagraph()
}

function Write-RichParagraph($sel, [string]$text, [double]$size, [int]$bold, [int]$italic, [int]$color, [int]$align) {
    $sel.Font.Name = 'Calibri'
    Set-FontSize $sel $size
    $sel.Font.Bold = $bold
    $sel.Font.Italic = $italic
    $sel.Font.Underline = 0
    $sel.Font.Color = $color
    $sel.ParagraphFormat.Alignment = $align
    $sel.ParagraphFormat.SpaceBefore = 0
    $sel.ParagraphFormat.SpaceAfter = 6
    $sel.TypeText($text)
    $sel.TypeParagraph()
}

function Move-AfterTable($sel, $table) {
    # Move to the very end of the document (after the table) and open a new
    # paragraph, instead of relying on the table range end position.
    $sel.EndKey(6) | Out-Null                            # wdStory = 6
    $sel.TypeParagraph()
}

# -----------------------------------------------------------------------------
# Complex blocks
# -----------------------------------------------------------------------------
function Add-Figure($sel, $doc, $node) {
    $script:figureNum++
    $figLabel = "Figura $($script:figureNum) — $($node.caption)"
    $relPath  = ($node.file -replace '/', '\')
    $absPath  = Join-Path $repoRoot $relPath

    if (Test-Path -LiteralPath $absPath) {
        # Embedded image (15 cm wide; logo uses a different size in the cover)
        Reset-BodyFormat $sel
        $sel.ParagraphFormat.Alignment = 1               # center
        $sel.ParagraphFormat.SpaceAfter = 4
        $shape = $doc.InlineShapes.AddPicture($absPath, $false, $true, $sel.Range)
        $shape.LockAspectRatio = -1
        $shape.Width = 425.2                             # 15 cm in points
        $sel.TypeParagraph()
        Write-Caption $sel $figLabel
    } else {
        # Visible pending placeholder: red label + yellow 1x1 boxed table
        $script:placeholderCount++
        Reset-BodyFormat $sel
        Write-RichParagraph $sel "$figLabel (captura pendiente)" 11 1 1 $COLOR_RED 1

        $t = $doc.Tables.Add($sel.Range, 1, 1)
        $t.Borders.Enable = 1
        $cellRange = $t.Cell(1, 1).Range
        $cellRange.Text = "CAPTURA PENDIENTE — $($node.desc_pendiente) · Archivo esperado: $($node.file)"
        $cellRange.Shading.BackgroundPatternColor = $COLOR_YELLOW
        $cellRange.Font.Name = 'Calibri'
        Set-FontSize $cellRange 10.5
        $cellRange.Font.Bold = 1
        $cellRange.Font.Color = 0
        $t.AutoFitBehavior(2)                            # fit to window
        Move-AfterTable $sel $t
    }
}

function Add-Note($sel, $doc, [string]$variant, [string]$text) {
    $color = $COLOR_INFO
    if ($variant -eq 'tip')  { $color = $COLOR_TIP }
    if ($variant -eq 'warn') { $color = $COLOR_WARN }

    $t = $doc.Tables.Add($sel.Range, 1, 1)
    $t.Borders.Enable = 1
    $cellRange = $t.Cell(1, 1).Range
    $cellRange.Text = $text
    $cellRange.Shading.BackgroundPatternColor = $color
    $cellRange.Font.Name = 'Calibri'
    Set-FontSize $cellRange 10.5
    $cellRange.Font.Bold = 0
    $cellRange.Font.Color = 0
    $t.AutoFitBehavior(2)
    Move-AfterTable $sel $t
}

function Add-DataTable($sel, $doc, $headers, $rows) {
    $nRows = @($rows).Count + 1
    $nCols = @($headers).Count
    if ($nCols -lt 1) { return }

    $t = $doc.Tables.Add($sel.Range, $nRows, $nCols)
    $t.Borders.Enable = 1

    for ($c = 1; $c -le $nCols; $c++) {
        $t.Cell(1, $c).Range.Text = [string]$headers[$c - 1]
    }
    $r = 2
    foreach ($row in $rows) {
        for ($c = 1; $c -le $nCols; $c++) {
            $value = ''
            if (($c - 1) -lt @($row).Count) { $value = [string]$row[$c - 1] }
            $t.Cell($r, $c).Range.Text = $value
        }
        $r++
    }

    $t.Range.Font.Name = 'Calibri'
    Set-FontSize $t.Range 10.5
    $t.Range.Font.Color = 0
    try { $t.Rows.Item(1).Range.Font.Bold = 1 } catch { }
    for ($c = 1; $c -le $nCols; $c++) {
        $t.Cell(1, $c).Range.Shading.BackgroundPatternColor = $COLOR_HEADER
    }
    $t.AutoFitBehavior(2)
    Move-AfterTable $sel $t
}

function Add-Bullets($sel, $items) {
    foreach ($item in $items) {
        Reset-BodyFormat $sel
        $sel.ParagraphFormat.Alignment = 0               # left
        $sel.Range.ListFormat.ApplyBulletDefault()
        $sel.TypeText([string]$item)
        $sel.TypeParagraph()
    }
    $sel.Range.ListFormat.RemoveNumbers()
}

function Add-Steps($sel, $items) {
    foreach ($item in $items) {
        Reset-BodyFormat $sel
        $sel.ParagraphFormat.Alignment = 0               # left
        $sel.Range.ListFormat.ApplyNumberDefault()
        $sel.TypeText([string]$item)
        $sel.TypeParagraph()
    }
    $sel.Range.ListFormat.RemoveNumbers()
}

# -----------------------------------------------------------------------------
# Build the document
# -----------------------------------------------------------------------------
$word = New-Object -ComObject Word.Application
$doc  = $null

try {
    $word.Visible = $false
    $word.DisplayAlerts = 0
    # Performance: stop background repagination/spell checking, which also
    # prevents intermittent COM property-set rejections on large documents.
    try { $word.ScreenUpdating = $false } catch { }
    try { $word.Options.Pagination = $false } catch { }
    try { $word.Options.CheckSpellingAsYouType = $false } catch { }
    try { $word.Options.CheckGrammarAsYouType = $false } catch { }

    $script:sw = [System.Diagnostics.Stopwatch]::StartNew()

    $doc = $word.Documents.Add()
    $sel = $word.Selection

    # Page setup: Letter, 72 pt margins
    try { $doc.PageSetup.PaperSize = 2 } catch { }
    try {
        $doc.PageSetup.PageWidth  = 612
        $doc.PageSetup.PageHeight = 792
    } catch { }
    $doc.PageSetup.TopMargin    = 72
    $doc.PageSetup.BottomMargin = 72
    $doc.PageSetup.LeftMargin   = 72
    $doc.PageSetup.RightMargin  = 72

    # -------------------------------------------------------------------------
    # COVER PAGE
    # -------------------------------------------------------------------------
    if (Test-Path -LiteralPath $logoAbs) {
        $sel.ParagraphFormat.Alignment = 1
        $sel.ParagraphFormat.SpaceAfter = 18
        $logoShape = $doc.InlineShapes.AddPicture($logoAbs, $false, $true, $sel.Range)
        $logoShape.LockAspectRatio = -1
        $logoShape.Width = 127.56                        # 4.5 cm
        $sel.TypeParagraph()
    }

    Reset-BodyFormat $sel
    try { $sel.Style = -63 } catch { }                   # wdStyleTitle
    $sel.Font.Name = 'Calibri'
    Set-FontSize $sel 28
    $sel.Font.Bold = 1
    $sel.Font.Italic = 0
    $sel.Font.Color = $COLOR_H1
    $sel.ParagraphFormat.Alignment = 1
    $sel.ParagraphFormat.SpaceAfter = 10
    $sel.TypeText("MANUAL DE USUARIO FINAL")
    $sel.TypeParagraph()

    Write-CenteredLine $sel ([string]$meta.sistema) 14
    Write-CenteredLine $sel 'Conjunto Residencial "Las Mesetas de Morón" — Condominio Digital' 12

    Reset-BodyFormat $sel
    $sel.ParagraphFormat.Alignment = 1
    $sel.ParagraphFormat.SpaceAfter = 2
    $sel.TypeParagraph()

    Write-CenteredLine $sel "Proyecto: $($meta.proyecto)" 11
    Write-CenteredLine $sel "Unidad Curricular: $($meta.uc)" 11
    Write-CenteredLine $sel ([string]$meta.universidad) 11
    Write-CenteredLine $sel "Autores:" 11
    foreach ($autor in $meta.autores) {
        Write-CenteredLine $sel ([string]$autor) 11
    }
    Write-CenteredLine $sel "Docente: $($meta.docente)" 11
    Write-CenteredLine $sel "Versión $($meta.version) — $($meta.fecha)" 11

    $sel.InsertBreak(7)                                  # wdPageBreak

    # -------------------------------------------------------------------------
    # VERSION CONTROL
    # -------------------------------------------------------------------------
    Write-Heading $sel 1 'Control de Versiones'
    $vcRows = @()
    foreach ($vc in $meta.control_versiones) { $vcRows += ,@($vc) }
    Add-DataTable $sel $doc @('Versión', 'Fecha', 'Descripción') $vcRows
    $sel.InsertBreak(7)

    # -------------------------------------------------------------------------
    # CONTENT NODES
    # -------------------------------------------------------------------------
    $nodeIndex = 0
    foreach ($node in $nodos) {
        $nodeIndex++
        if (($nodeIndex % 25) -eq 0) {
            Write-Host "  ... nodo $nodeIndex / $($nodos.Count)"
        }
        try {
        switch ($node.type) {
            'toc' {
                Write-Heading $sel 1 'Índice'
                foreach ($item in $node.items) {
                    Reset-BodyFormat $sel
                    $sel.ParagraphFormat.Alignment = 0
                    $sel.ParagraphFormat.SpaceAfter = 2
                    $sel.TypeText([string]$item)
                    $sel.TypeParagraph()
                }
                $sel.InsertBreak(7)
            }
            'pagebreak' {
                $sel.InsertBreak(7)
            }
            'part' {
                $sel.InsertBreak(7)                      # wdPageBreak
                Reset-BodyFormat $sel
                $sel.Font.Name = 'Calibri'
                Set-FontSize $sel 20
                $sel.Font.Bold = 1
                $sel.Font.Italic = 0
                $sel.Font.Underline = 0
                $sel.Font.Color = $COLOR_H1
                $sel.ParagraphFormat.Alignment = 1       # center
                $sel.ParagraphFormat.SpaceBefore = 0
                $sel.ParagraphFormat.SpaceAfter = 18
                try { $sel.ParagraphFormat.LineSpacingRule = 0 } catch { }   # single
                $sel.TypeText([string]$node.text)
                $sel.TypeParagraph()
                Reset-BodyFormat $sel
                $sel.ParagraphFormat.SpaceAfter = 12
                $sel.TypeParagraph()                     # extra spacing after the part title
            }
            'h1' { Write-Heading $sel 1 ([string]$node.text) }
            'h2' { Write-Heading $sel 2 ([string]$node.text) }
            'h3' { Write-Heading $sel 3 ([string]$node.text) }
            'p'  { Write-Paragraph $sel ([string]$node.text) }
            'bullets' { Add-Bullets $sel $node.items }
            'steps'   { Add-Steps $sel $node.items }
            'note'    { Add-Note $sel $doc ([string]$node.variant) ([string]$node.text) }
            'image'   { Add-Figure $sel $doc $node }
            'table'   { Add-DataTable $sel $doc $node.headers $node.rows }
            default   { }
        }
        } catch {
            Write-Host "FALLO EN NODO #$nodeIndex (tipo=$($node.type)): $($_.Exception.GetType().Name): $($_.Exception.Message)"
            throw
        }
    }
    Write-Host "  ... nodo loop terminado en $([math]::Round($script:sw.Elapsed.TotalSeconds,1)) s"

    # -------------------------------------------------------------------------
    # FOOTER (best effort, non-blocking)
    # -------------------------------------------------------------------------
    try {
        $sec = $doc.Sections.Item(1)
        $f = $sec.Footers.Item(1)
        $f.Range.Text = "Manual de Usuario — Condominio Digital — Página "
        $footerRange = $f.Range
        $footerRange.Collapse(0)                         # collapse to end of footer text
        $footerRange.Fields.Add($footerRange, -1, 'PAGE') | Out-Null
        $f.Range.ParagraphFormat.Alignment = 1
        $f.Range.Font.Name = 'Calibri'
        Set-FontSize $f.Range 9
        $f.Range.Font.Color = $COLOR_GRAY
    } catch {
        Write-Host "Aviso: no se pudo agregar el pie de pagina: $($_.Exception.Message)"
    }
    $sec = $null; $f = $null; $footerRange = $null

    # -------------------------------------------------------------------------
    # SAVE OUTPUTS
    # SaveAs2 is executed by a short auxiliary process that attaches to the
    # running Word instance (COM ROT) while the builder stays idle. Calling
    # SaveAs2 in the same process that built the document can stall Word in
    # some Windows automation environments; saving from an attaching helper is
    # reliable and keeps the script self-contained.
    # -------------------------------------------------------------------------
    $markerPath = Join-Path $env:TEMP 'condominio_manual_save_marker.txt'
    Remove-Item -LiteralPath $markerPath -Force -ErrorAction SilentlyContinue

    $helperCode = @'
try {
    $ErrorActionPreference = 'Stop'
    $helperWord = [System.Runtime.InteropServices.Marshal]::GetActiveObject('Word.Application')
    $helperDoc = $helperWord.Documents.Item(1)
    $helperDoc.SaveAs2('__DOCX__', 16)
    [System.IO.File]::WriteAllText('__MARKER__', 'docx-ok')
    $helperDoc.ExportAsFixedFormat('__PDF__', 17)
    [System.IO.File]::WriteAllText('__MARKER__', 'ok')
} catch {
    [System.IO.File]::WriteAllText('__MARKER__', 'fail: ' + $_.Exception.Message)
}
'@
    $helperCode = $helperCode.Replace('__DOCX__', $docxAbs).Replace('__PDF__', $pdfAbs).Replace('__MARKER__', $markerPath)
    $helperB64  = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($helperCode))

    Write-Host '  ... guardando DOCX y PDF (proceso auxiliar)...'
    $helperProc = Start-Process powershell -ArgumentList @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-EncodedCommand', $helperB64) -PassThru
    try { Wait-Process -Id $helperProc.Id -Timeout 300 -ErrorAction Stop } catch { try { Stop-Process -Id $helperProc.Id -Force } catch { } }

    $helperResult = ''
    if (Test-Path -LiteralPath $markerPath) { $helperResult = [System.IO.File]::ReadAllText($markerPath) }
    if (-not $helperResult.StartsWith('ok')) {
        Write-Host "Aviso: guardado auxiliar no confirmado ($helperResult). Intento directo..."
        if (-not (Test-Path -LiteralPath $docxAbs)) { $doc.SaveAs2($docxAbs, 16) }
        if (-not (Test-Path -LiteralPath $pdfAbs))  { $doc.ExportAsFixedFormat($pdfAbs, 17) }
    }
    Remove-Item -LiteralPath $markerPath -Force -ErrorAction SilentlyContinue

    # -------------------------------------------------------------------------
    # SUMMARY
    # -------------------------------------------------------------------------
    $docxBytes = 0
    $pdfBytes  = 0
    if (Test-Path -LiteralPath $docxAbs) { $docxBytes = (Get-Item -LiteralPath $docxAbs).Length }
    if (Test-Path -LiteralPath $pdfAbs)  { $pdfBytes  = (Get-Item -LiteralPath $pdfAbs).Length }
    $fullText = $doc.Content.Text
    $phCount  = ([regex]::Matches($fullText, 'CAPTURA PENDIENTE')).Count

    Write-Host 'BUILD OK'
    Write-Host "DOCX...............: $docxAbs ($docxBytes bytes)"
    Write-Host "PDF................: $pdfAbs ($pdfBytes bytes)"
    Write-Host "Parrafos...........: $($doc.Paragraphs.Count)"
    Write-Host "InlineShapes.......: $($doc.InlineShapes.Count)"
    Write-Host "Tablas.............: $($doc.Tables.Count)"
    Write-Host "Figuras totales....: $figureTotal"
    Write-Host "Placeholders CAPTURA PENDIENTE: $phCount"
    Write-Host "Reintentos Font.Size: $($script:fontRetries)"
    Write-Host "Tiempo total.......: $([math]::Round($script:sw.Elapsed.TotalSeconds,1)) s"
}
finally {
    if ($doc -ne $null) {
        try { $doc.Close(0) } catch { }                  # wdDoNotSaveChanges
    }
    try { $word.Quit() } catch { }
    try { [System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null } catch { }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
