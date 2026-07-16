'use strict';

/**
 * HKL Website Forensics Suite
 * Asynchronous Scan Engine
 *
 * Stuurt de batchscan aan via de JSON-API.
 */
class HKLScanEngine {
    constructor(options = {}) {
        this.apiUrl = options.apiUrl ?? '/api.php';
        this.batchSize = options.batchSize ?? 100;
        this.delayMs = options.delayMs ?? 150;

        this.scanId = null;
        this.startedAt = null;
        this.running = false;
        this.cancelled = false;

        this.onStarted = options.onStarted ?? (() => {});
        this.onProgress = options.onProgress ?? (() => {});
        this.onCompleted = options.onCompleted ?? (() => {});
        this.onError = options.onError ?? (() => {});
    }

    /**
     * Start een nieuwe scan en verwerk daarna automatisch
     * alle batches.
     */
    async start(scanPath) {
        const normalizedPath = String(scanPath ?? '').trim();

        if (normalizedPath === '') {
            throw new Error('Geen scanpad opgegeven.');
        }

        if (this.running) {
            throw new Error('Er loopt al een scan.');
        }

        this.running = true;
        this.cancelled = false;
        this.startedAt = Date.now();

        try {
            const response = await this.request('/api/scan/start', {
                scan_path: normalizedPath,
            });

            if (response.success !== true) {
                throw new Error(
                    response.message ?? 'De scan kon niet worden gestart.'
                );
            }

            this.scanId = response.scan_id;

            const initialProgress = this.enrichProgress(
                response.progress ?? {}
            );

            this.onStarted({
                scanId: this.scanId,
                progress: initialProgress,
            });

            this.onProgress(initialProgress);

            if (initialProgress.status === 'completed') {
                this.finish(initialProgress);

                return initialProgress;
            }

            return await this.processUntilCompleted();
        } catch (error) {
            this.fail(error);

            throw error;
        }
    }

    /**
     * Verwerk steeds één batch totdat de scan gereed is.
     */
    async processUntilCompleted() {
        while (this.running && !this.cancelled) {
            const response = await this.request('/api/scan/batch', {
                scan_id: this.scanId,
                batch_size: this.batchSize,
            });

            if (response.success !== true) {
                throw new Error(
                    response.message ?? 'De batch kon niet worden verwerkt.'
                );
            }

            const progress = this.enrichProgress(
                response.progress ?? {}
            );

            this.onProgress(progress);

            if (progress.status === 'completed') {
                this.finish(progress);

                return progress;
            }

            await this.sleep(this.delayMs);
        }

        return null;
    }

    /**
     * Lees de actuele voortgang zonder een batch te verwerken.
     */
    async getProgress() {
        if (!this.scanId) {
            throw new Error('Er is nog geen scan-ID beschikbaar.');
        }

        const response = await this.request('/api/scan/progress', {
            scan_id: this.scanId,
        });

        if (response.success !== true) {
            throw new Error(
                response.message ?? 'Voortgang kon niet worden gelezen.'
            );
        }

        return this.enrichProgress(response.progress ?? {});
    }

    /**
     * Stop het automatisch aanvragen van nieuwe batches.
     *
     * De reeds verwerkte scanresultaten blijven behouden.
     */
    cancel() {
        this.cancelled = true;
        this.running = false;
    }

    finish(progress) {
        this.running = false;
        this.cancelled = false;

        this.onCompleted(progress);
    }

    fail(error) {
        this.running = false;

        const normalizedError = error instanceof Error
            ? error
            : new Error(String(error));

        this.onError(normalizedError);
    }

    /**
     * Voeg browserberekeningen toe, waaronder verstreken
     * en geschatte resterende tijd.
     */
    enrichProgress(progress) {
        const total = Number(progress.total_files ?? 0);
        const processed = Number(progress.processed_files ?? 0);

        const percentage = total > 0
            ? Math.min(100, (processed / total) * 100)
            : Number(progress.percentage ?? 0);

        const elapsedSeconds = this.startedAt
            ? Math.max(0, (Date.now() - this.startedAt) / 1000)
            : 0;

        let remainingSeconds = null;

        if (
            processed > 0
            && total > processed
            && elapsedSeconds > 0
        ) {
            const secondsPerFile = elapsedSeconds / processed;

            remainingSeconds = Math.round(
                (total - processed) * secondsPerFile
            );
        }

        return {
            ...progress,
            total_files: total,
            processed_files: processed,
            failed_files: Number(progress.failed_files ?? 0),
            percentage: Math.round(percentage * 100) / 100,
            elapsed_seconds: Math.round(elapsedSeconds),
            remaining_seconds: remainingSeconds,
            elapsed_human: this.formatDuration(elapsedSeconds),
            remaining_human: remainingSeconds === null
                ? 'Wordt berekend…'
                : this.formatDuration(remainingSeconds),
        };
    }

    /**
     * Voer een JSON-aanvraag uit.
     */
    async request(endpoint, payload) {
        const response = await fetch(
            `${this.apiUrl}?endpoint=${encodeURIComponent(endpoint)}`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
                cache: 'no-store',
            }
        );

        let data;

        try {
            data = await response.json();
        } catch {
            throw new Error(
                `De server gaf geen geldig JSON-antwoord `
                + `(HTTP ${response.status}).`
            );
        }

        if (!response.ok) {
            throw new Error(
                data.message
                ?? `API-aanvraag mislukt (HTTP ${response.status}).`
            );
        }

        return data;
    }

    sleep(milliseconds) {
        return new Promise((resolve) => {
            window.setTimeout(resolve, milliseconds);
        });
    }

    formatDuration(seconds) {
        const totalSeconds = Math.max(0, Math.round(seconds));

        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const remainingSeconds = totalSeconds % 60;

        if (hours > 0) {
            return [
                String(hours).padStart(2, '0'),
                String(minutes).padStart(2, '0'),
                String(remainingSeconds).padStart(2, '0'),
            ].join(':');
        }

        return [
            String(minutes).padStart(2, '0'),
            String(remainingSeconds).padStart(2, '0'),
        ].join(':');
    }
}

window.HKLScanEngine = HKLScanEngine;