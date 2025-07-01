/**
 * Custom Quiz System - Admin JavaScript
 */

(function($) {
    'use strict';

    class CustomQuizAdmin {
        constructor() {
            this.init();
        }

        init() {
            this.setupQuestionTypeToggle();
            this.setupPreviewUpdate();
            this.setupImportValidation();
            this.setupBulkActions();
            this.setupSettingsValidation();
            this.setupDashboardCharts();
        }

        setupQuestionTypeToggle() {
            $('#cqz_type').on('change', function() {
                const type = $(this).val();
                const choicesRow = $('.cqz-choices-row');
                const textInputRow = $('input[name="cqz_text_input"]').closest('tr');

                if (type === 'text') {
                    choicesRow.addClass('hidden');
                    textInputRow.addClass('hidden');
                } else {
                    choicesRow.removeClass('hidden');
                    textInputRow.removeClass('hidden');
                }

                // Update preview
                CustomQuizAdmin.updatePreview();
            });
        }

        setupPreviewUpdate() {
            // Update preview when form fields change
            $('#cqz_choices, #cqz_text_input').on('input change', function() {
                CustomQuizAdmin.updatePreview();
            });

            // Initial preview update
            CustomQuizAdmin.updatePreview();
        }

        static updatePreview() {
            const type = $('#cqz_type').val();
            const choices = $('#cqz_choices').val();
            const textInput = $('#cqz_text_input').is(':checked');
            const questionTitle = $('#title').val();

            let previewHtml = '';

            if (questionTitle) {
                previewHtml += `<p><strong>${questionTitle}</strong></p>`;
            }

            if (type === 'single' && choices) {
                const choiceArray = choices.split('\n').filter(choice => choice.trim());
                choiceArray.forEach(choice => {
                    previewHtml += `<label><input type="radio" disabled> ${choice.trim()}</label><br>`;
                });
            } else if (type === 'multiple' && choices) {
                const choiceArray = choices.split('\n').filter(choice => choice.trim());
                choiceArray.forEach(choice => {
                    previewHtml += `<label><input type="checkbox" disabled> ${choice.trim()}</label><br>`;
                });
            }

            if (textInput || type === 'text') {
                previewHtml += `<input type="text" placeholder="Your answer..." disabled style="width: 100%; margin-top: 10px;" />`;
            }

            $('.cqz-preview').html(previewHtml);
        }

        setupImportValidation() {
            $('#cqz_csv').on('change', function() {
                const file = this.files[0];
                if (file) {
                    CustomQuizAdmin.validateCSVFile(file);
                }
            });

            // CSV template download
            $('.cqz-download-template').on('click', function(e) {
                e.preventDefault();
                CustomQuizAdmin.downloadCSVTemplate();
            });
        }

        static validateCSVFile(file) {
            if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
                CustomQuizAdmin.showNotification('Please select a valid CSV file.', 'error');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const csv = e.target.result;
                const lines = csv.split('\n');
                const header = lines[0].split(',');

                const requiredColumns = ['question', 'type', 'correct'];
                const missingColumns = requiredColumns.filter(col => 
                    !header.some(h => h.trim().toLowerCase() === col)
                );

                if (missingColumns.length > 0) {
                    CustomQuizAdmin.showNotification(
                        `Missing required columns: ${missingColumns.join(', ')}`, 
                        'error'
                    );
                } else {
                    CustomQuizAdmin.showNotification('CSV file is valid!', 'success');
                }
            };
            reader.readAsText(file);
        }

        static downloadCSVTemplate() {
            const template = [
                'question,type,choices,correct,category,points,explanation,content',
                'What is the capital of France?,single,"Paris\nLondon\nBerlin\nMadrid",Paris,Geography,1,Paris is the capital of France.,',
                'Which planets are in our solar system?,multiple,"Mercury\nVenus\nEarth\nMars\nJupiter\nSaturn\nUranus\nNeptune","Mercury,Venus,Earth,Mars,Jupiter,Saturn,Uranus,Neptune",Science,2,These are the 8 planets in our solar system.,',
                'What is 2 + 2?,text,,4,Math,1,Basic arithmetic question.,'
            ].join('\n');

            const blob = new Blob([template], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'quiz_questions_template.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        setupBulkActions() {
            // Bulk delete
            $('#doaction, #doaction2').on('click', function(e) {
                const action = $(this).prev('select').val();
                if (action === 'trash' || action === 'delete') {
                    if (!confirm('Are you sure you want to delete the selected questions?')) {
                        e.preventDefault();
                    }
                }
            });

            // Bulk category assignment
            $('.cqz-bulk-category').on('change', function() {
                const categoryId = $(this).val();
                const checkedBoxes = $('.cqz-question-checkbox:checked');

                if (categoryId && checkedBoxes.length > 0) {
                    CustomQuizAdmin.assignCategoryToQuestions(checkedBoxes, categoryId);
                }
            });
        }

        static assignCategoryToQuestions(checkboxes, categoryId) {
            const questionIds = checkboxes.map(function() {
                return $(this).val();
            }).get();

            $.ajax({
                url: cqz_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'cqz_assign_category',
                    nonce: cqz_admin.nonce,
                    question_ids: questionIds,
                    category_id: categoryId
                },
                success: function(response) {
                    if (response.success) {
                        CustomQuizAdmin.showNotification('Category assigned successfully!', 'success');
                        location.reload();
                    } else {
                        CustomQuizAdmin.showNotification('Error assigning category.', 'error');
                    }
                }
            });
        }

        setupSettingsValidation() {
            $('#cqz_time_limit, #cqz_questions_per_category').on('input', function() {
                const value = parseInt($(this).val());
                const min = parseInt($(this).attr('min'));
                const max = parseInt($(this).attr('max'));

                if (value < min || value > max) {
                    $(this).addClass('error');
                    $(this).next('.description').addClass('error');
                } else {
                    $(this).removeClass('error');
                    $(this).next('.description').removeClass('error');
                }
            });

            $('#cqz_admin_email').on('input', function() {
                const email = $(this).val();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (email && !emailRegex.test(email)) {
                    $(this).addClass('error');
                    $(this).next('.description').addClass('error');
                } else {
                    $(this).removeClass('error');
                    $(this).next('.description').removeClass('error');
                }
            });
        }

        setupDashboardCharts() {
            if ($('#cqz-results-chart').length) {
                CustomQuizAdmin.initResultsChart();
            }

            if ($('#cqz-categories-chart').length) {
                CustomQuizAdmin.initCategoriesChart();
            }
        }

        static initResultsChart() {
            // This would integrate with a charting library like Chart.js
            // For now, we'll create a simple visualization
            const ctx = document.getElementById('cqz-results-chart').getContext('2d');
            
            // Mock data - in real implementation, this would come from AJAX
            const data = {
                labels: ['0-20%', '21-40%', '41-60%', '61-80%', '81-100%'],
                datasets: [{
                    label: 'Quiz Results Distribution',
                    data: [5, 12, 25, 35, 23],
                    backgroundColor: [
                        '#dc3545',
                        '#fd7e14',
                        '#ffc107',
                        '#28a745',
                        '#20c997'
                    ]
                }]
            };

            // If Chart.js is available
            if (typeof Chart !== 'undefined') {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
        }

        static initCategoriesChart() {
            const ctx = document.getElementById('cqz-categories-chart').getContext('2d');
            
            // Mock data
            const data = {
                labels: ['General Knowledge', 'Science', 'History', 'Geography'],
                datasets: [{
                    label: 'Questions per Category',
                    data: [45, 38, 32, 28],
                    backgroundColor: '#667eea'
                }]
            };

            if (typeof Chart !== 'undefined') {
                new Chart(ctx, {
                    type: 'bar',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        }

        static showNotification(message, type = 'info') {
            const notification = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            $('.wrap h1').after(notification);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notification.fadeOut();
            }, 5000);

            // Manual dismiss
            notification.find('.notice-dismiss').on('click', function() {
                notification.fadeOut();
            });
        }

        // Utility functions
        static formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        }

        static formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }

        static debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new CustomQuizAdmin();
    });

    // Global functions for external use
    window.CustomQuizAdmin = CustomQuizAdmin;

    // Add global function for admin result view
    window.cqz_view_result = function(resultId) {
        // Use the correct admin page slug for results
        var url = window.location.origin + window.location.pathname + '?page=quiz-results&view_result=' + resultId;
        window.open(url, '_blank');
    };

})(jQuery); 