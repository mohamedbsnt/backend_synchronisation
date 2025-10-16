<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncGoogleMerchantProducts extends Command
{
    protected $signature = 'google:sync-products {--save-file}';
    protected $description = 'Synchronise les produits avec Google Merchant Center';

    public function handle(): int
    {
        $this->info('🚀 Synchronisation Google Merchant Center démarrée...');
        $this->newLine();

        try {
            // Test de l'API source
            $response = Http::timeout(30)->get('https://hanaball.devaito.com/api/google-all-products');
            
            if (!$response->successful()) {
                $this->error('❌ Erreur lors de l\'accès à l\'API: HTTP ' . $response->status());
                return Command::FAILURE;
            }

            $csvContent = $response->body();
            $lines = explode("\n", trim($csvContent));
            $productCount = count($lines) - 1; // -1 pour l'en-tête

            $this->info("✅ Flux CSV généré avec succès!");
            $this->table(['Métrique', 'Valeur'], [
                ['Format', 'CSV Google Merchant'],
                ['Produits', $productCount],
                ['Taille', $this->formatBytes(strlen($csvContent))],
                ['URL', 'https://hanaball.devaito.com/api/google-all-products'],
            ]);

            // Sauvegarder si demandé
            if ($this->option('save-file')) {
                $filename = 'google_merchant_' . date('Y-m-d_H-i-s') . '.csv';
                Storage::disk('public')->put($filename, $csvContent);
                
                $this->newLine();
                $this->info('💾 Fichier sauvegardé: storage/app/public/' . $filename);
                $this->info('🌐 URL publique: ' . url('storage/' . $filename));
            }

            $this->newLine();
            $this->comment('💡 Configuration Google Merchant Center:');
            $this->line('   1. Allez sur https://merchants.google.com');
            $this->line('   2. Produits → Flux → Créer un flux');
            $this->line('   3. URL: https://hanaball.devaito.com/api/google-all-products');
            $this->line('   4. Fréquence: Quotidienne');

            Log::info('Synchronisation Google Merchant réussie', [
                'products_count' => $productCount,
                'csv_size' => strlen($csvContent)
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('💥 Erreur: ' . $e->getMessage());
            Log::error('Erreur synchronisation Google Merchant', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return Command::FAILURE;
        }
    }

    private function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
