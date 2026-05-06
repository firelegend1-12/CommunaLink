param(
    [string]$ProjectId = "communalink-web",
    [string]$SourceVersion = "20260408t061126",
    [switch]$NoPromote
)

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
Set-Location $repoRoot

$envJson = gcloud app versions describe $SourceVersion --project=$ProjectId --service=default --format='json(envVariables)'
$envObj = $envJson | ConvertFrom-Json
$envVars = $envObj.envVariables
if ($null -eq $envVars) {
    $envVars = $envObj
}

if ($null -eq $envVars) {
    throw "No env vars found on source version $SourceVersion."
}

function Escape-Yaml([string]$value) {
    if ($null -eq $value) {
        return ""
    }
    return ($value -replace '"', '\\"')
}

$lines = New-Object System.Collections.Generic.List[string]
$lines.Add('runtime: php82')
$lines.Add('env: standard')
$lines.Add('instance_class: F1')
$lines.Add('')
$lines.Add('automatic_scaling:')
$lines.Add('  max_instances: 1')
$lines.Add('  min_instances: 0')
$lines.Add('')
$lines.Add('handlers:')
$lines.Add('    - { url: /assets, static_dir: assets }')
$lines.Add('    - { url: /uploads, static_dir: uploads }')
$lines.Add('    - { url: /admin/images, static_dir: admin/images }')
$lines.Add('    - { url: /resident/manifest.json, static_files: resident/manifest.json, upload: resident/manifest.json }')
$lines.Add('    - { url: /(.+\\.(css|js|map|png|jpg|jpeg|gif|svg|ico|webp|woff|woff2|ttf|eot))$, static_files: "\\1", upload: .+\\.(css|js|map|png|jpg|jpeg|gif|svg|ico|webp|woff|woff2|ttf|eot)$ }')
$lines.Add('    - { url: /(.+\\.php)$, script: auto }')
$lines.Add('')
$lines.Add('env_variables:')

foreach ($prop in $envVars.PSObject.Properties) {
    $key = $prop.Name
    $escaped = Escape-Yaml ([string]$prop.Value)
    $lines.Add(('  {0}: "{1}"' -f $key, $escaped))
}

$tempYaml = Join-Path $repoRoot ('.app.deploy.generated.' + $ProjectId + '.yaml')
Set-Content -Path $tempYaml -Value ($lines -join "`r`n") -Encoding UTF8

try {
    $deployArgs = @($tempYaml, "--project=$ProjectId", "--quiet")
    if ($NoPromote) {
        $deployArgs += "--no-promote"
    }

    & gcloud app deploy @deployArgs
    if ($LASTEXITCODE -ne 0) {
        throw "App Engine deployment failed for $ProjectId."
    }
}
finally {
    if (Test-Path $tempYaml) {
        Remove-Item $tempYaml -Force
    }
}

$newVersion = (gcloud app versions list --project=$ProjectId --service=default --format='value(id)' --sort-by='~id' | Select-Object -First 1).Trim()
$defaultHost = (gcloud app describe --project=$ProjectId --format='value(defaultHostname)').Trim()
$versionUrl = "https://$newVersion.$defaultHost"

$envJsonNew = gcloud app versions describe $newVersion --project=$ProjectId --service=default --format=json
$envObjNew = $envJsonNew | ConvertFrom-Json
$envKeys = @()
if ($envObjNew.envVariables) {
    $envKeys = $envObjNew.envVariables.PSObject.Properties.Name
}

Write-Output ("NEW_VERSION=$newVersion")
Write-Output ("ENV_KEY_COUNT=$($envKeys.Count)")
Write-Output ("VERSION_URL=$versionUrl")

try {
    $ready = Invoke-WebRequest -Uri ($versionUrl + '/api/ready.php') -UseBasicParsing -TimeoutSec 45
    Write-Output ("VERSION_READY_STATUS=$([int]$ready.StatusCode)")
} catch {
    Write-Output "VERSION_READY_STATUS=FAILED"
}
