'use strict';

/**
 * HKL Website Forensics Suite
 * MediaWiki Risk Engine
 */

class HKLRiskEngine {

    constructor(options = {}) {

        this.apiUrl = options.apiUrl ?? '/api.php';

        this.batchSize = options.batchSize ?? 250;

        this.delay = options.delay ?? 100;

        this.scanId = null;

        this.running = false;

        this.onStarted =
            options.onStarted ?? (() => {});

        this.onProgress =
            options.onProgress ?? (() => {});

        this.onCompleted =
            options.onCompleted ?? (() => {});

        this.onError =
            options.onError ?? (() => {});
    }

    async start(scanId) {

        this.scanId = scanId;

        this.running = true;

        const response = await this.call(
            '/api/risk/start',
            {
                scan_id: scanId
            }
        );

        if (!response.success) {

            this.onError(response.message);

            return;
        }

        this.onStarted(response.progress);

        await this.loop();
    }

    async loop() {

        while (this.running) {

            const response = await this.call(
                '/api/risk/batch',
                {
                    scan_id: this.scanId,
                    batch_size: this.batchSize
                }
            );

            if (!response.success) {

                this.running = false;

                this.onError(
                    response.message
                );

                return;
            }

            const progress =
                response.progress;

            this.onProgress(progress);

            if (
                progress.status ===
                'completed'
            ) {

                this.running = false;

            const response =
                await this.call(
                    '/api/risk/findings',
                    {
                        scan_id: this.scanId
                    }
                );

            const findings =
                Array.isArray(response.findings)
                    ? response.findings
                    : [];

            findings.sort(

                (left, right) => {

                    const weight = {

                        critical: 4,

                        high: 3,

                        medium: 2,

                        low: 1

                    };

                    return (

                        (weight[right.severity] ?? 0)

                        -

                        (weight[left.severity] ?? 0)

                    );

                }

            );

            this.onCompleted(

                progress,

                findings

            );

            return;
                
            }

            await this.sleep(this.delay);

        }

    }

    async call(endpoint, payload) {

        const response =
            await fetch(

                this.apiUrl
                + '?endpoint='
                + encodeURIComponent(endpoint),

                {

                    method: 'POST',

                    headers: {

                        'Content-Type':
                            'application/json'

                    },

                    body: JSON.stringify(
                        payload
                    )

                }

            );

        return response.json();

    }

    sleep(ms) {

        return new Promise(

            resolve =>
                setTimeout(
                    resolve,
                    ms
                )

        );

    }

}

window.HKLRiskEngine =
    HKLRiskEngine;