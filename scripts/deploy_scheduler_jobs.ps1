param(
    [Parameter(Mandatory = $true)]
    [string]$ProjectId,

    [Parameter(Mandatory = $true)]
    [string]$Region,

    [Parameter(Mandatory = $true)]
    [string]$ServiceBaseUrl,

    [string]$PermitJobName = "communalink-permit-expiry-check",
    [string]$SessionCleanupJobName = "communalink-session-cleanup",

    [string]$PermitSchedule = "0 9 * * *",
    [string]$SessionCleanupSchedule = "*/10 * * * *",

    [Parameter(Mandatory = $true)]
    [string]$PermitSchedulerToken,

    [string]$SessionCleanupSchedulerToken = ""
)

$ErrorActionPreference = "Stop"

if ([string]::IsNullOrWhiteSpace($SessionCleanupSchedulerToken)) {
    $SessionCleanupSchedulerToken = $PermitSchedulerToken
}

$permitUri = "$ServiceBaseUrl/api/check-expiring-permits.php"
$cleanupUri = "$ServiceBaseUrl/api/cleanup-active-sessions.php"

Write-Host "Configuring gcloud project $ProjectId in region $Region"
gcloud config set project $ProjectId | Out-Null

Write-Host "Upserting Cloud Scheduler job: $PermitJobName"
gcloud scheduler jobs delete $PermitJobName --location=$Region --quiet 2>$null

gcloud scheduler jobs create http $PermitJobName `
  --location=$Region `
  --schedule="$PermitSchedule" `
  --time-zone="Asia/Manila" `
  --uri="$permitUri" `
  --http-method=POST `
  --headers="X-Cloud-Scheduler-Token=$PermitSchedulerToken"

Write-Host "Upserting Cloud Scheduler job: $SessionCleanupJobName"
gcloud scheduler jobs delete $SessionCleanupJobName --location=$Region --quiet 2>$null

gcloud scheduler jobs create http $SessionCleanupJobName `
  --location=$Region `
  --schedule="$SessionCleanupSchedule" `
  --time-zone="Asia/Manila" `
  --uri="$cleanupUri" `
  --http-method=POST `
  --headers="X-Cloud-Scheduler-Token=$SessionCleanupSchedulerToken"

Write-Host "Scheduler deployment complete."
