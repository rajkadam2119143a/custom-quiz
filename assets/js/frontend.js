/**
 * Custom Quiz System - Frontend JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Global variables
    let quizTimer = null;
    let assignmentId = null;
    let timeLimit = 0;
    let answeredQuestions = new Set();
    let autoSaveInterval = null;
    let isSubmitting = false; // Move to top-level scope for all triggers
    
    // Initialize quiz interface
    window.initQuizInterface = function(assignmentIdParam, timeLimitParam) {
        assignmentId = assignmentIdParam;
        timeLimit = timeLimitParam;
        
        initializeTimer();
        initializeProgress();
        initializeQuestionHandlers();
        initializeAutoSave();
        initializeFormSubmission();
        initializeBeforeUnload();

        // Force status update for pre-filled answers on page load
        $('.cqz-question').each(function() {
            const questionId = $(this).data('question-id');
            const type = $(this).data('type');
            let isAnswered = false;
            if (type === 'single') {
                isAnswered = $(this).find('.cqz-radio-input:checked').length > 0;
            } else if (type === 'multiple') {
                isAnswered = $(this).find('.cqz-checkbox-input:checked').length > 0;
            } else if (type === 'text') {
                isAnswered = $(this).find('.cqz-textarea').val().trim().length > 0;
            }
            if (isAnswered) {
                answeredQuestions.add(questionId);
                updateQuestionStatus(questionId, 'answered');
            } else {
                updateQuestionStatus(questionId, 'unanswered');
            }
        });
    };
    
    // Also set assignmentId from data attribute if available
    $(document).ready(function() {
        const quizContainer = $('#cqz-quiz-container');
        if (quizContainer.length && !assignmentId) {
            assignmentId = quizContainer.data('assignment-id');
            if (assignmentId) {
                const timeLimit = $('#cqz-timer').data('time-limit') || 7200; // Default 2 hours
                initQuizInterface(assignmentId, timeLimit);
            }
        }
        
        // Handle URL parameters for quiz state
        handleQuizStateFromURL();
    });
    
    // Handle quiz state from URL parameters
    function handleQuizStateFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        
        if (action === 'quizstart') {
            // Quiz is in progress - ensure quiz interface is shown
            if ($('#cqz-quiz-container').length) {
                const assignmentId = $('#cqz-quiz-container').data('assignment-id');
                if (assignmentId) {
                    const timeLimit = $('#cqz-timer').data('time-limit') || 7200;
                    initQuizInterface(assignmentId, timeLimit);
                }
            }
        } else if (action === 'quiz_result') {
            // Show results page - this will be handled by the backend
            // The backend should render the results when this parameter is present
            console.log('Showing quiz results from URL parameter');
        }
    }
    
    // Update URL with quiz state
    function updateQuizURL(action, assignmentId = null) {
        const url = new URL(window.location);
        url.searchParams.set('action', action);
        if (assignmentId) {
            url.searchParams.set('assignment_id', assignmentId);
        }
        window.history.pushState({}, '', url);
    }
    
    // Timer functionality
    function initializeTimer() {
        const timerElement = $('#cqz-timer');
        if (!timerElement.length) return;
        
        let timeRemaining = timeLimit;
        
        function updateTimer() {
            const hours = Math.floor(timeRemaining / 3600);
            const minutes = Math.floor((timeRemaining % 3600) / 60);
            const seconds = timeRemaining % 60;
            
            const timeString = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            timerElement.find('.cqz-timer-display').text(timeString);
            
            // Change color when time is running low
            if (timeRemaining <= 300) { // 5 minutes
                timerElement.addClass('cqz-timer-warning');
            }
            if (timeRemaining <= 60) { // 1 minute
                timerElement.addClass('cqz-timer-danger');
            }
            
            if (timeRemaining <= 0) {
                clearInterval(quizTimer);
                autoSubmitQuiz();
                return;
            }
            
            timeRemaining--;
        }
        
        updateTimer();
        quizTimer = setInterval(updateTimer, 1000);
    }
    
    // Progress tracking
    function initializeProgress() {
        const progressElement = $('#cqz-progress');
        if (!progressElement.length) return;
        
        function updateProgress() {
            const totalQuestions = $('.cqz-question').length;
            const answeredCount = answeredQuestions.size;
            const percentage = totalQuestions > 0 ? (answeredCount / totalQuestions) * 100 : 0;
            
            progressElement.find('.cqz-progress-fill').css('width', percentage + '%');
            progressElement.find('.cqz-progress-text').text(`${answeredCount} / ${totalQuestions}`);
        }
        
        // Update progress every 5 seconds (keep for backup)
        setInterval(updateProgress, 5000);
        updateProgress();

        // Make updateProgress globally available
        window.cqzUpdateProgress = updateProgress;
    }
    
    // Question handlers
    function initializeQuestionHandlers() {
        // Handle radio button changes, ensuring no duplicate handlers are attached
        $(document).off('change.cqz', '.cqz-radio-input').on('change.cqz', '.cqz-radio-input', function() {
            const questionId = $(this).closest('.cqz-question').data('question-id');
            const answer = $(this).val();
            answeredQuestions.add(questionId);
            updateQuestionStatus(questionId, 'answered');
            saveAnswer(questionId, answer);
            if (window.cqzUpdateProgress) window.cqzUpdateProgress(); // Instant progress update
        });
        
        // Handle checkbox changes, ensuring no duplicate handlers are attached
        $(document).off('change.cqz', '.cqz-checkbox-input').on('change.cqz', '.cqz-checkbox-input', function() {
            const questionId = $(this).closest('.cqz-question').data('question-id');
            const checked = $(this).closest('.cqz-question').find('.cqz-checkbox-input:checked').length > 0;
            const answers = [];
            if (checked) {
                answeredQuestions.add(questionId);
                updateQuestionStatus(questionId, 'answered');
                // Collect all checked answers for this question
                $(this).closest('.cqz-question').find('.cqz-checkbox-input:checked').each(function() {
                    answers.push($(this).val());
                });
            } else {
                answeredQuestions.delete(questionId);
                updateQuestionStatus(questionId, 'unanswered');
            }
            saveAnswer(questionId, answers);
            if (window.cqzUpdateProgress) window.cqzUpdateProgress(); // Instant progress update
        });
        
        // Handle text input changes, ensuring no duplicate handlers are attached
        $(document).off('input.cqz', '.cqz-textarea').on('input.cqz', '.cqz-textarea', function() {
            const questionId = $(this).closest('.cqz-question').data('question-id');
            const val = $(this).val().trim();
            
            if (val) {
                answeredQuestions.add(questionId);
                updateQuestionStatus(questionId, 'answered');
            } else {
                answeredQuestions.delete(questionId);
                updateQuestionStatus(questionId, 'unanswered');
            }
            saveAnswer(questionId, val);
            if (window.cqzUpdateProgress) window.cqzUpdateProgress(); // Instant progress update
        });
    }
    
    // Update question status
    function updateQuestionStatus(questionId, status) {
        const questionElement = $(`.cqz-question[data-question-id="${questionId}"]`);
        const statusElement = questionElement.find('.cqz-question-status');
        
        statusElement.attr('data-status', status);
        
        switch (status) {
            case 'answered':
                statusElement.text('Answered').removeClass('cqz-unanswered').addClass('cqz-answered');
                break;
            case 'unanswered':
                statusElement.text('Not answered').removeClass('cqz-answered').addClass('cqz-unanswered');
                break;
        }
    }
    
    // Save answer to server
    function saveAnswer(questionId, answer) {
        $.post(cqz_frontend.ajax_url, {
            action: 'cqz_save_answer',
            nonce: cqz_frontend.nonce,
            assignment_id: assignmentId,
            question_id: questionId,
            answer: answer
        }, function(response) {
            if (response.success) {
                // Answer saved successfully
                console.log('Answer saved:', response.data);
            } else {
                console.error('Failed to save answer:', response.data);
                showNotification('Failed to save answer. Please try again.', 'error');
            }
        }).fail(function(xhr, status, error) {
            console.error('Error saving answer');
            let errorMessage = 'Network error while saving answer.';
            if (xhr.status === 403 || xhr.status === 401) {
                errorMessage = 'You must be logged in to save answers. Please refresh the page and log in.';
            }
            showNotification(errorMessage, 'error');
        });
    }
    
    // Auto-save functionality
    function initializeAutoSave() {
        autoSaveInterval = setInterval(function() {
            if (answeredQuestions.size > 0) {
                console.log('Auto-saving progress...');
                // Progress is automatically saved when answers change
            }
        }, 30000); // Auto-save every 30 seconds
    }
    
    // Form submission
    function initializeFormSubmission() {
        $(document).off('submit.cqz', '#cqz-quiz-form').on('submit.cqz', '#cqz-quiz-form', function(e) {
            e.preventDefault();
            if (isSubmitting) return;
            
            if (confirm(cqz_frontend.strings.confirm_submit)) {
                isSubmitting = true;
                showSubmissionProgress();
                submitQuiz(function(success) {
                    if (success) {
                        sessionStorage.removeItem('cqz_quiz_state');
                    }
                    isSubmitting = false;
                });
            }
        });
        // Save progress button
        $('#cqz-save-progress').on('click', function() {
            const btn = $(this);
            btn.prop('disabled', true).text(cqz_frontend.strings.saving_progress);
            // Progress is already saved automatically, just show confirmation
            setTimeout(function() {
                btn.prop('disabled', false).text('Save Progress');
                showNotification(cqz_frontend.strings.progress_saved, 'success');
            }, 1000);
        });
    }
    
    // Submit quiz
    function submitQuiz(doneCallback) {
        const form = $('#cqz-quiz-form');
        const submitBtn = form.find('#cqz-submit-quiz');
        console.log('Submitting quiz with assignmentId:', assignmentId);
        submitBtn.prop('disabled', true).text('Submitting...');
        // Collect all answers
        const answers = {};
        const textAnswers = {};
        $('.cqz-question').each(function() {
            const questionId = $(this).data('question-id');
            const questionType = $(this).data('type');
            if (questionType === 'text') {
                const textAnswer = $(this).find('.cqz-textarea').val().trim();
                if (textAnswer) {
                    answers[questionId] = textAnswer;
                }
            } else {
                const selectedAnswers = [];
                $(this).find('input:checked').each(function() {
                    selectedAnswers.push($(this).val());
                });
                if (selectedAnswers.length > 0) {
                    answers[questionId] = selectedAnswers;
                }
            }
        });
        console.log('Collected answers:', answers);
        console.log('Collected text answers:', textAnswers);
        // Submit to server
        $.post(cqz_frontend.ajax_url, {
            action: 'cqz_submit_quiz',
            nonce: cqz_frontend.nonce,
            assignment_id: assignmentId,
            answers: answers,
            text_answers: textAnswers,
            start_time: form.find('input[name="start_time"]').val(),
            end_time: Math.floor(Date.now() / 1000)
        }, function(response) {
            console.log('Quiz submission response:', response);
            if (response.success) {
                clearInterval(quizTimer);
                clearInterval(autoSaveInterval);
                // Remove sticky header (timer + progress bar)
                $('.cqz-sticky-header').remove();
                // Show results
                $('.cqz-quiz-container').html(response.data.html);
                // Remove beforeunload warning
                $(window).off('beforeunload');
                // Update URL to show quiz results
                updateQuizURL('quiz_result', assignmentId);
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
                console.log('Quiz submitted successfully, results displayed');
                showNotification('Quiz submitted successfully!', 'success');
                // On success, remove the progress overlay and display the results
                $('.cqz-submission-progress').remove();
                $('#cqz-quiz-app-container').html(response.data.html);
                sessionStorage.removeItem('cqz_quiz_state');
            } else {
                submitBtn.prop('disabled', false).text('Submit Quiz');
                // On error, remove the progress overlay and show a notification
                $('.cqz-submission-progress').remove();
                showNotification(response.data.message || cqz_frontend.strings.error_occurred, 'error');
            }
            if (typeof doneCallback === 'function') doneCallback();
        }).fail(function(xhr, status, error) {
            console.error('Quiz submission failed:', xhr, status, error);
            submitBtn.prop('disabled', false).text('Submit Quiz');
            let errorMessage = 'Network error. Please check your connection and try again.';
            if (xhr.status === 403 || xhr.status === 401) {
                errorMessage = 'You must be logged in to submit the quiz. Please refresh the page and log in.';
            }
            showNotification(errorMessage, 'error');
            if (typeof doneCallback === 'function') doneCallback();
        });
    }
    
    // Auto-submit when time expires
    function autoSubmitQuiz() {
        if (isSubmitting) return; // Prevent double auto/manual submit
        isSubmitting = true;
        showNotification(cqz_frontend.strings.time_up, 'warning');
        setTimeout(function() {
            const submitBtn = $('#cqz-submit-quiz');
            submitBtn.prop('disabled', true).text('Submitting...');
            submitQuiz(function() {
                isSubmitting = false;
                submitBtn.prop('disabled', false).text('Submit Quiz');
            });
        }, 2000);
    }
    
    // Before unload warning
    function initializeBeforeUnload() {
        $(window).on('beforeunload', function(e) {
            if (answeredQuestions.size > 0) {
                e.preventDefault();
                e.returnValue = cqz_frontend.strings.confirm_leave;
                return cqz_frontend.strings.confirm_leave;
            }
        });
    }
    
    // Show notification
    function showNotification(message, type = 'info') {
        // Create notification container if it doesn't exist
        let container = $('.cqz-notification-container');
        if (!container.length) {
            container = $('<div class="cqz-notification-container"></div>');
            $('body').append(container);
        }
        
        // Create notification element
        const notification = $(`
            <div class="cqz-notification ${type}">
                <div class="cqz-notification-content">${message}</div>
                <button class="cqz-notification-close">&times;</button>
            </div>
        `);
        
        // Add to container
        container.append(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Handle close button
        notification.find('.cqz-notification-close').on('click', function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (quizTimer) {
            clearInterval(quizTimer);
        }
        if (autoSaveInterval) {
            clearInterval(autoSaveInterval);
        }
    });

    // Helper to get a fresh nonce before starting assessment
    function getFreshNonce(callback) {
        $.post(cqz_frontend.ajax_url, { action: 'cqz_get_nonce' }, function(response) {
            if (response.success) {
                callback(response.data.start_assessment_nonce);
            } else {
                showNotification('Could not get security token. Please refresh the page.', 'error');
            }
        });
    }

    // Handle start assessment button click
    $(document).on('click', '#cqz-start-assessment', function() {
        const button = $(this);
        const originalText = button.text();
        button.prop('disabled', true).text(cqz_frontend.strings.starting_assessment);

        getFreshNonce(function(nonce) {
            $.ajax({
                url: cqz_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'cqz_start_assessment',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Replace the landing page with quiz interface
                        $('.cqz-assessment-landing').html(response.data.html);
                        // Initialize quiz interface
                        if (typeof initQuizInterface === 'function') {
                            initQuizInterface(response.data.assignment_id, response.data.time_limit);
                        }
                        // Update URL
                        updateQuizURL('quizstart', response.data.assignment_id);
                        showNotification(cqz_frontend.strings.assessment_started, 'success');
						location.reload();
                    } else {
                        showNotification(response.data || cqz_frontend.strings.error_occurred, 'error');
                        button.prop('disabled', false).text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = cqz_frontend.strings.error_occurred;
                    if (xhr.status === 403 || xhr.status === 401) {
                        errorMessage = 'You must be logged in to take the quiz. Please refresh the page and log in.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = xhr.responseJSON.data;
                    }
                    showNotification(errorMessage, 'error');
                    button.prop('disabled', false).text(originalText);
                }
            });
        });
    });

    // Take New Quiz button handler
    $(document).on('click', '#cqz-take-new-quiz', function(e) {
        e.preventDefault();
        // Reload the page to show the welcome/start screen
        window.location.href = window.location.pathname;
    });

    // Show submission progress
    function showSubmissionProgress() {
        const progressHtml = `
            <div class="cqz-submission-progress">
                <div class="cqz-progress-message">
                    <div class="cqz-spinner"></div>
                    <p>Your result is being saved. Your report is being generated...</p>
                </div>
            </div>
        `;
        // Use a container that is always present, like the body or a main app wrapper
        $('body').append(progressHtml);
    }
    
    // Check and restore quiz state from sessionStorage
    function checkAndRestoreQuizState() {
        // ... existing code ...
    }
}); 