$ErrorActionPreference = 'Stop'

$projectPath = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$projectPathLower = $projectPath.ToLowerInvariant()

Write-Host '[veshop] Limpando instancias anteriores do Vite...'

# Evita multiplas instancias do Vite para o mesmo projeto.
try {
    Get-CimInstance Win32_Process -ErrorAction Stop |
        Where-Object {
            if ($_.Name -ne 'node.exe') {
                return $false
            }

            $cmd = [string] $_.CommandLine
            if ([string]::IsNullOrWhiteSpace($cmd)) {
                return $false
            }

            $cmdLower = $cmd.ToLowerInvariant()
            return $cmdLower.Contains($projectPathLower) -and ($cmdLower -match 'vite[\\/]+bin[\\/]+vite\.js')
        } |
        ForEach-Object {
            Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
        }
} catch {
    Write-Warning '[veshop] Sem permissao para consultar processos via CIM. Continuando sem encerrar instancias antigas.'
}

$hotFile = Join-Path $projectPath 'public\hot'
if (Test-Path $hotFile) {
    Remove-Item $hotFile -Force -ErrorAction SilentlyContinue
}

Write-Host '[veshop] Iniciando servidor em http://127.0.0.1:5173'

Set-Location $projectPath
try {
    npx vite --host 127.0.0.1 --port 5173 --strictPort --clearScreen false
} finally {
    if (Test-Path $hotFile) {
        Remove-Item $hotFile -Force -ErrorAction SilentlyContinue
    }
}
