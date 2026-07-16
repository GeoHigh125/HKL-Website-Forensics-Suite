'use strict';

/**
 * HKL Website Forensics Suite
 *
 * Algemene dashboardcomponent voor inventarisatie
 * en risicoanalyse.
 */
class HKLDashboard {
    constructor(options = {}) {
        this.inventoryContainer = this.requireElement(
            options.inventoryContainerId ?? 'scan-dashboard'
        );

        this.riskContainer = this.requireElement(
            options.riskContainerId ?? 'risk-dashboard'
        );

        this.findingsContainer = this.requireElement(
            options.findingsContainerId ?? 'risk-findings'
        );
    }

    /**
     * Zet het inventarisatiedashboard terug naar de beginstatus.
     */
    resetInventory() {
        this.setText('scan-status', 'Niet gestart');
        this.setText('scan-phase', '-');
        this.setText('scan-files', '0 / 0');
        this.setText('scan-current', '-');
        this.setText('scan-percent', '0 %');
        this.setText('scan-elapsed', '00:00');
        this.setText('scan-remaining', '-');

        this.setProgressBar(
            'scan-progress-bar',
            'scan-progress-label',
            0
        );

        this.inventoryContainer.classList.remove(
            'scan-completed',
            'scan-error'
        );
    }

    /**
     * Toont de voortgang van de bestandsinventarisatie.
     */
    updateInventory(progress = {}) {
        const percentage = this.normalizePercentage(
            progress.percentage
        );

        this.setText(
            'scan-status',
            this.translateStatus(progress.status)
        );

        this.setText(
            'scan-phase',
            this.translatePhase(progress.phase)
        );

        this.setText(
            'scan-files',
            `${Number(progress.processed_files ?? 0)} / `
            + `${Number(progress.total_files ?? 0)}`
        );

        this.setText(
            'scan-current',
            progress.current_file
                ?? progress.last_file
                ?? '-'
        );

        this.setText(
            'scan-percent',
            `${percentage.toFixed(2)} %`
        );

        this.setText(
            'scan-elapsed',
            progress.elapsed_human ?? '00:00'
        );

        this.setText(
            'scan-remaining',
            progress.remaining_human ?? '-'
        );

        this.setProgressBar(
            'scan-progress-bar',
            'scan-progress-label',
            percentage
        );
    }

    completeInventory(progress = {}) {
        const completedProgress = {
            ...progress,
            percentage: 100,
            remaining_human: '00:00',
            status: 'completed',
            phase: 'completed',
        };

        this.updateInventory(completedProgress);

        this.inventoryContainer.classList.add(
            'scan-completed'
        );
    }

    inventoryError(message) {
        this.inventoryContainer.classList.add(
            'scan-error'
        );

        this.setText(
            'scan-status',
            message || 'Onbekende fout'
        );
    }

    /**
     * Zet het risicodashboard terug naar de beginstatus.
     */
    resetRisk() {
        this.setText('risk-status', 'Niet gestart');
        this.setText('risk-phase', '-');
        this.setText('risk-records', '0 / 0');
        this.setText('risk-current', '-');
        this.setText('risk-percent', '0 %');
        this.setText('risk-critical', '0');
        this.setText('risk-high', '0');
        this.setText('risk-medium', '0');
        this.setText('risk-low', '0');
        this.setText('risk-total', '0');

        this.setProgressBar(
            'risk-progress-bar',
            'risk-progress-label',
            0
        );

        this.riskContainer.classList.remove(
            'scan-completed',
            'scan-error'
        );

        this.findingsContainer.innerHTML = '';
    }

    /**
     * Toont de voortgang van de risicoanalyse.
     */
    updateRisk(progress = {}) {
        const percentage = this.normalizePercentage(
            progress.percentage
        );

        this.setText(
            'risk-status',
            this.translateStatus(progress.status)
        );

        this.setText(
            'risk-phase',
            this.translatePhase(progress.phase)
        );

        this.setText(
            'risk-records',
            `${Number(progress.processed_records ?? 0)} / `
            + `${Number(progress.total_records ?? 0)}`
        );

        this.setText(
            'risk-current',
            progress.current_file
                ?? progress.last_file
                ?? '-'
        );

        this.setText(
            'risk-percent',
            `${percentage.toFixed(2)} %`
        );

        this.setText(
            'risk-critical',
            Number(progress.critical ?? 0)
        );

        this.setText(
            'risk-high',
            Number(progress.high ?? 0)
        );

        this.setText(
            'risk-medium',
            Number(progress.medium ?? 0)
        );

        this.setText(
            'risk-low',
            Number(progress.low ?? 0)
        );

        this.setText(
            'risk-total',
            Number(progress.findings ?? 0)
        );

        this.setProgressBar(
            'risk-progress-bar',
            'risk-progress-label',
            percentage
        );
    }

    completeRisk(progress = {}, findings = []) {
        const completedProgress = {
            ...progress,
            percentage: 100,
            status: 'completed',
            phase: 'completed',
        };

        this.updateRisk(completedProgress);

        this.riskContainer.classList.add(
            'scan-completed'
        );

        this.renderFindings(findings);
    }

    riskError(message) {
        this.riskContainer.classList.add(
            'scan-error'
        );

        this.setText(
            'risk-status',
            message || 'Onbekende fout'
        );
    }

    /**
     * Toont de gevonden risico's.
     */
    renderFindings(findings = []) {
        this.findingsContainer.innerHTML = '';

        if (!Array.isArray(findings) || findings.length === 0) {
            const message = document.createElement('p');

            message.textContent =
                'Geen risicovondsten aangetroffen.';

            this.findingsContainer.appendChild(message);

            return;
        }

        const table = document.createElement('table');

        table.className = 'findings-table';

        const thead = document.createElement('thead');

        thead.innerHTML = `
            <tr>
                <th>Ernst</th>
                <th>Bestand</th>
                <th>Categorie</th>
                <th>Reden</th>
                <th>Indicatoren</th>
            </tr>
        `;

        table.appendChild(thead);

        const tbody = document.createElement('tbody');

        findings.forEach((finding) => {
            const row = document.createElement('tr');

            const severity = String(
                finding.severity ?? 'low'
            );

            row.classList.add(
                `severity-${severity}`
            );

            row.appendChild(
                this.createCell(
                    this.translateSeverity(severity)
                )
            );

            row.appendChild(
                this.createCodeCell(
                    finding.path ?? ''
                )
            );

            row.appendChild(
                this.createCell(
                    finding.category ?? ''
                )
            );

            row.appendChild(
                this.createCell(
                    finding.reason ?? ''
                )
            );

            const indicators = Array.isArray(
                finding.indicators
            )
                ? finding.indicators.join(', ')
                : '';

            row.appendChild(
                this.createCell(indicators)
            );

            tbody.appendChild(row);
        });

        table.appendChild(tbody);

        this.findingsContainer.appendChild(table);
    }

    setProgressBar(
        barId,
        labelId,
        percentage
    ) {
        const normalized = this.normalizePercentage(
            percentage
        );

        const bar = document.getElementById(barId);

        if (bar) {
            bar.style.width = `${normalized}%`;
        }

        const label = document.getElementById(labelId);

        if (label) {
            label.textContent =
                `${normalized.toFixed(2)} %`;
        }
    }

    setText(id, value) {
        const element = document.getElementById(id);

        if (!element) {
            return;
        }

        element.textContent = String(value);
    }

    normalizePercentage(value) {
        const percentage = Number(value ?? 0);

        if (!Number.isFinite(percentage)) {
            return 0;
        }

        return Math.min(
            100,
            Math.max(0, percentage)
        );
    }

    translateStatus(status) {
        return {
            pending: 'In afwachting',
            running: 'Bezig',
            completed: 'Voltooid',
            failed: 'Mislukt',
        }[status] ?? status ?? '-';
    }

    translatePhase(phase) {
        return {
            inventory: 'Bestandsinventarisatie',
            'risk-analysis': 'Risicoanalyse',
            completed: 'Voltooid',
        }[phase] ?? phase ?? '-';
    }

    translateSeverity(severity) {
        return {
            critical: 'Kritiek',
            high: 'Hoog',
            medium: 'Middel',
            low: 'Laag',
        }[severity] ?? severity;
    }

    requireElement(id) {
        const element = document.getElementById(id);

        if (!element) {
            throw new Error(
                `Dashboardelement niet gevonden: ${id}`
            );
        }

        return element;
    }

    createCell(value) {
        const cell = document.createElement('td');

        cell.textContent = String(value);

        return cell;
    }

    createCodeCell(value) {
        const cell = document.createElement('td');
        const code = document.createElement('code');

        code.textContent = String(value);

        cell.appendChild(code);

        return cell;
    }
}

window.HKLDashboard = HKLDashboard;