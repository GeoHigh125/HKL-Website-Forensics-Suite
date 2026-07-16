'use strict';

/**
 * HKL Website Forensics Suite
 * Dashboard Component
 */

class HKLScanDashboard {

    constructor(containerId) {

        this.container = document.getElementById(containerId);

        if (!this.container) {

            throw new Error(
                'Dashboard container niet gevonden.'
            );

        }

    }

    update(progress) {

        this.setText(
            'scan-status',
            progress.status ?? '-'
        );

        this.setText(
            'scan-phase',
            progress.phase ?? '-'
        );

        this.setText(
            'scan-files',
            `${progress.processed_files} / ${progress.total_files}`
        );

        this.setText(
            'scan-current',
            progress.current_file
                ?? progress.last_file
                ?? '-'
        );

        this.setText(
            'scan-percent',
            `${progress.percentage.toFixed(2)} %`
        );

        this.setText(
            'scan-elapsed',
            progress.elapsed_human
        );

        this.setText(
            'scan-remaining',
            progress.remaining_human
        );

        this.updateProgressBar(
            progress.percentage
        );

    }

    completed(progress) {
        const completedProgress = {
            ...progress,
            remaining_human: '00:00',
            current_file: progress.current_file ?? '-',
        };

        this.update(completedProgress);

        this.container.classList.add(
            'scan-completed'
        );
    }

    error(message) {

        this.container.classList.add(
            'scan-error'
        );

        this.setText(
            'scan-status',
            message
        );

    }

    updateProgressBar(percent) {
        const bar = document.getElementById(
            'scan-progress-bar'
        );

        if (bar) {
            bar.style.width = `${percent}%`;
        }

        const label = document.getElementById(
            'scan-progress-label'
        );

        if (label) {
            label.textContent =
                `${Number(percent).toFixed(2)} %`;
        }
    }

    setText(id, value) {

        const element =
            document.getElementById(id);

        if (!element) {
            return;
        }

        element.textContent =
            String(value);

    }

}

window.HKLScanDashboard =
    HKLScanDashboard;