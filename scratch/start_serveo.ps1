# Start serveo on port 443 in the background
$outputFile = "scratch/serveo_output.txt"
Remove-Item $outputFile -ErrorAction SilentlyContinue

Start-Process -FilePath "ssh" -ArgumentList "-o StrictHostKeyChecking=no -R 80:localhost:443 serveo.net" -RedirectStandardOutput $outputFile -NoNewWindow -PassThru

Write-Host "Starting Serveo tunnel on port 443..."
Start-Sleep -Seconds 5

if (Test-Path $outputFile) {
    $content = Get-Content $outputFile -Raw
    Write-Host "Serveo output: $content"
    
    # Try to extract the URL (looks like https://*.serveousercontent.com or https://*.serveo.net)
    if ($content -match "Forwarding HTTP traffic from (https://[a-zA-Z0-9.-]+)") {
        $tunnelUrl = $Matches[1]
        Write-Host "Successfully generated Serveo tunnel URL: $tunnelUrl"
        
        # Update .env file
        $envFile = ".env"
        $envContent = Get-Content $envFile -Raw
        $envContent = $envContent -replace "APP_URL=https://[a-zA-Z0-9.-]+", "APP_URL=$tunnelUrl"
        Set-Content -Path $envFile -Value $envContent -NoNewline
        Write-Host "Updated .env file with new APP_URL!"
        
        # Update shopify.app.toml
        $tomlFile = "shopify.app.toml"
        $tomlContent = Get-Content $tomlFile -Raw
        $tomlContent = $tomlContent -replace "application_url = `"https://[a-zA-Z0-9.-]+`"", "application_url = `"$tunnelUrl`""
        $tomlContent = $tomlContent -replace "url = `"https://[a-zA-Z0-9.-]+`"", "url = `"$tunnelUrl`""
        $tomlContent = $tomlContent -replace "https://[a-zA-Z0-9.-]+/authenticate", "$tunnelUrl/authenticate"
        Set-Content -Path $tomlFile -Value $tomlContent -NoNewline
        Write-Host "Updated shopify.app.toml with new URL!"
        
        # Clear Laravel Cache
        php artisan config:clear
        php artisan cache:clear
        
        # Deploy config to Shopify
        Write-Host "Deploying new configuration to Shopify Partner Dashboard..."
        npx shopify app deploy --allow-updates
        
        Write-Host "`nTo start Shopify App Dev, run this command in your terminal:"
        Write-Host "npx shopify app dev --localhost-port 8080 --tunnel-url $tunnelUrl:443 --theme-app-extension-port 9294`n"
    } else {
        Write-Host "Could not find URL in output yet. Let's wait another 5 seconds..."
        Start-Sleep -Seconds 5
        $content = Get-Content $outputFile -Raw
        if ($content -match "Forwarding HTTP traffic from (https://[a-zA-Z0-9.-]+)") {
            $tunnelUrl = $Matches[1]
            Write-Host "Successfully generated Serveo tunnel URL: $tunnelUrl"
            
            # Update .env file
            $envFile = ".env"
            $envContent = Get-Content $envFile -Raw
            $envContent = $envContent -replace "APP_URL=https://[a-zA-Z0-9.-]+", "APP_URL=$tunnelUrl"
            Set-Content -Path $envFile -Value $envContent -NoNewline
            Write-Host "Updated .env file with new APP_URL!"
            
            # Update shopify.app.toml
            $tomlFile = "shopify.app.toml"
            $tomlContent = Get-Content $tomlFile -Raw
            $tomlContent = $tomlContent -replace "application_url = `"https://[a-zA-Z0-9.-]+`"", "application_url = `"$tunnelUrl`""
            $tomlContent = $tomlContent -replace "url = `"https://[a-zA-Z0-9.-]+`"", "url = `"$tunnelUrl`""
            $tomlContent = $tomlContent -replace "https://[a-zA-Z0-9.-]+/authenticate", "$tunnelUrl/authenticate"
            Set-Content -Path $tomlFile -Value $tomlContent -NoNewline
            Write-Host "Updated shopify.app.toml with new URL!"
            
            # Clear Laravel Cache
            php artisan config:clear
            php artisan cache:clear
            
            # Deploy config to Shopify
            Write-Host "Deploying new configuration to Shopify Partner Dashboard..."
            npx shopify app deploy --allow-updates
            
            Write-Host "`nTo start Shopify App Dev, run this command in your terminal:"
            Write-Host "npx shopify app dev --localhost-port 8080 --tunnel-url $tunnelUrl:443 --theme-app-extension-port 9294`n"
        } else {
            Write-Host "Failed to extract tunnel URL. Please check scratch/serveo_output.txt"
        }
    }
} else {
    Write-Host "Output file not created. Serveo might have failed to start."
}
