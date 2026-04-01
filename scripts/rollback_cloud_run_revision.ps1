param(
    [Parameter(Mandatory = $true)]
    [string]$ProjectId,

    [Parameter(Mandatory = $true)]
    [string]$Region,

    [Parameter(Mandatory = $true)]
    [string]$ServiceName,

    [string]$TargetRevision = "",

    [ValidateRange(1, 100)]
    [int]$TrafficPercent = 100,

    [switch]$WhatIf
)

$ErrorActionPreference = "Stop"

Write-Host "Configuring gcloud project $ProjectId in region $Region"
gcloud config set project $ProjectId | Out-Null

if ([string]::IsNullOrWhiteSpace($TargetRevision)) {
    Write-Host "No target revision provided. Resolving previous ready revision for service $ServiceName"
    $revisions = gcloud run revisions list `
      --service=$ServiceName `
      --region=$Region `
      --sort-by="~metadata.creationTimestamp" `
      --format="value(metadata.name)"

    if ([string]::IsNullOrWhiteSpace($revisions)) {
        throw "No revisions found for service $ServiceName"
    }

    $revisionList = @($revisions -split "`r?`n" | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
    if ($revisionList.Count -lt 2) {
        throw "Need at least two revisions to roll back automatically. Provide -TargetRevision explicitly."
    }

    $TargetRevision = $revisionList[1]
    Write-Host "Auto-selected previous revision: $TargetRevision"
}

$trafficSpec = "$TargetRevision=$TrafficPercent"
$command = "gcloud run services update-traffic $ServiceName --region=$Region --to-revisions $trafficSpec"

if ($WhatIf) {
    Write-Host "WhatIf: $command"
    exit 0
}

Write-Host "Executing rollback traffic update: $trafficSpec"
gcloud run services update-traffic $ServiceName --region=$Region --to-revisions $trafficSpec | Out-Null

$serviceUrl = gcloud run services describe $ServiceName --region=$Region --format="value(status.url)"
Write-Host "Rollback completed. Service URL: $serviceUrl"
