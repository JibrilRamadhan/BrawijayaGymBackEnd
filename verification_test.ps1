$baseUrl = "http://127.0.0.1:8000/api"

function Test-Endpoint {
    param ($Name, $ScriptBlock)
    Write-Host "`n--- Testing $Name ---" -ForegroundColor Cyan
    try {
        & $ScriptBlock
    }
    catch {
        Write-Host "Action Failed: $_" -ForegroundColor Red
        if ($_.Exception.Response) {
            $stream = $_.Exception.Response.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($stream)
            $body = $reader.ReadToEnd()
            Write-Host "Response Body: $body" -ForegroundColor Yellow
        }
    }
}

Test-Endpoint "Get Plans" {
    $plans = Invoke-RestMethod -Uri "$baseUrl/plans" -Method Get
    Write-Host "Plans Count: $($plans.data.Count)" -ForegroundColor Green
    $plans.data | Format-Table id, name, type, price
}

$userEmail = "test_$(Get-Random)@example.com"
$username = "user_$(Get-Random)"
$password = "password"

Test-Endpoint "Register User ($username)" {
    $body = @{
        username = $username
        email    = $userEmail
        password = $password
    }
    $register = Invoke-RestMethod -Uri "$baseUrl/register" -Method Post -Body $body
    Write-Host "Registered: $($register.message)" -ForegroundColor Green
    $global:token = $register.access_token
}

if ($global:token) {
    Test-Endpoint "Get Profile (Me)" {
        $me = Invoke-RestMethod -Uri "$baseUrl/me" -Method Get -Headers @{ Authorization = "Bearer $global:token" }
        Write-Host "Logged in as: $($me.data.username) ($($me.data.email))" -ForegroundColor Green
    }

    Test-Endpoint "Join Trial Plan" {
        $body = @{
            plan_id = 1
            name    = "Test User"
            phone   = "08123456789"
        }
        $join = Invoke-RestMethod -Uri "$baseUrl/subscriptions/join" -Method Post -Headers @{ Authorization = "Bearer $global:token" } -Body $body
        Write-Host "Joined Plan: $($join.data.plan_name)" -ForegroundColor Green
        Write-Host "Subscription UUID: $($join.data.subscription_uuid)" -ForegroundColor Green
    }

    Test-Endpoint "Join Paid Plan (Harian - 3 Days)" {
        $body = @{
            plan_id = 2
            days    = 3
            name    = "Test User"
            phone   = "08123456789"
        }
        $join = Invoke-RestMethod -Uri "$baseUrl/subscriptions/join" -Method Post -Headers @{ Authorization = "Bearer $global:token" } -Body $body
        Write-Host "Payment Generated: $($join.message)" -ForegroundColor Green
        Write-Host "Order ID: $($join.data.order_id)" -ForegroundColor Green
        Write-Host "Snap Token: $($join.data.snap_token)" -ForegroundColor Green
    }
}
