'use strict';

/**
 * HKL Website Forensics Suite
 * Dashboard Component
 */

class HKLScanDashboard {

    constructor(containerId) {

        this.container =
            document.getElementById(containerId);

        this.riskContainer =
            document.getElementById(
                'risk-dashboard'
            );

        this.findingsContainer =
            document.getElementById(
                'risk-findings'
            );

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
            `${progress.processed_files ?? 0} / `
            + `${progress.total_files ?? 0}`
        );

        this.setText(
            'scan-current',
            progress.current_file
                ?? progress.last_file
                ?? '-'
        );

        const percentage =
            Number(progress.percentage ?? 0);

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

        this.updateProgressBar(
            percentage
        );

    }

    completed(progress) {

        const completedProgress = {
            ...progress,
            remaining_human: '00:00',
            status: 'completed',
            phase: 'completed',
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

    updateRisk(progress) {

        if (!this.riskContainer) {
            return;
        }

        this.setText(
            'risk-status',
            progress.status ?? '-'
        );

        this.setText(
            'risk-phase',
            progress.phase ?? '-'
        );

        this.setText(
            'risk-files',
            `${progress.processed_records ?? 0} / `
            + `${progress.total_records ?? 0}`
        );

        this.setText(
            'risk-current',
            progress.current_file
                ?? progress.last_file
                ?? '-'
        );

        const percentage =
            Number(progress.percentage ?? 0);

        this.setText(
            'risk-percent',
            `${percentage.toFixed(2)} %`
        );

        this.setText(
            'risk-critical',
            progress.critical ?? 0
        );

        this.setText(
            'risk-high',
            progress.high ?? 0
        );

        this.setText(
            'risk-medium',
            progress.medium ?? 0
        );

        this.setText(
            'risk-low',
            progress.low ?? 0
        );

        this.setText(
            'risk-total',
            progress.findings ?? 0
        );

        const bar =
            document.getElementById(
                'risk-progress-bar'
            );

        if (bar) {
            bar.style.width =
                `${percentage}%`;
        }

        const label =
            document.getElementById(
                'risk-progress-label'
            );

        if (label) {
            label.textContent =
                `${percentage.toFixed(2)} %`;
        }

    }

    completedRisk(
        progress,
        findings
    ) {

        const completedProgress = {
            ...progress,
            percentage: 100,
            status: 'completed',
            phase: 'completed',
        };

        this.updateRisk(
            completedProgress
        );

        if (this.riskContainer) {
            this.riskContainer.classList.add(
                'scan-completed'
            );
        }

        if (!this.findingsContainer) {
            return;
        }

        this.findingsContainer.innerHTML = '';

        if (
            !Array.isArray(findings)
            || findings.length === 0
        ) {
            const message =
                document.createElement('p');

            message.textContent =
                'Geen verdachte bestanden gevonden.';

            this.findingsContainer.appendChild(
                message
            );

            return;
        }

        const table =
            document.createElement('table');

        const thead =
            document.createElement('thead');

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

        const tbody =
            document.createElement('tbody');

        findings.forEach((finding) => {

            const row =
                document.createElement('tr');

            row.className =
                'severity-'
                + finding.severity;

            row.innerHTML = `

            <td>

            <strong>

            ${finding.severity.toUpperCase()}

            </strong>

            </td>

            <td>

            <code>

            ${finding.path}

            </code>

            </td>

            <td>

            ${finding.category}

            </td>

            <td>

            ${finding.reason}

            </td>

            <td>

            ${Array.isArray(finding.indicators)

                ? finding.indicators.join('<br>')

                : ''

            }

            </td>

`;

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

            const indicators =
                Array.isArray(
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

        this.findingsContainer.appendChild(
            table
        );

    }

    riskError(message) {

        if (this.riskContainer) {
            this.riskContainer.classList.add(
                'scan-error'
            );
        }

        this.setText(
            'risk-status',
            message
        );

    }

    updateProgressBar(percent) {

        const bar =
            document.getElementById(
                'scan-progress-bar'
            );

        if (bar) {
            bar.style.width =
                `${percent}%`;
        }

        const label =
            document.getElementById(
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

    createCell(value) {

        const cell =
            document.createElement('td');

        cell.textContent =
            String(value);

        return cell;

    }

    createCodeCell(value) {

        const cell =
            document.createElement('td');

        const code =
            document.createElement('code');

        code.textContent =
            String(value);

        cell.appendChild(code);

        return cell;

    }

}

window.HKLScanDashboard =
    HKLScanDashboard;