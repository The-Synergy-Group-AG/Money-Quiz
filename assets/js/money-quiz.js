/**
 * Money Quiz Frontend JavaScript
 * Version: 4.0.0
 */

(function($) {
    'use strict';

    // Quiz Controller
    var MoneyQuiz = {
        
        // Properties
        currentQuestion: 0,
        totalQuestions: 0,
        answers: {},
        container: null,
        form: null,
        
        // Initialize
        init: function() {
            this.container = $('.money-quiz-container');
            if (!this.container.length) return;
            
            this.form = this.container.find('.money-quiz-form');
            this.totalQuestions = this.container.find('.money-quiz-question').length;
            
            this.bindEvents();
            this.showQuestion(0);
            this.updateProgress();
        },
        
        // Bind events
        bindEvents: function() {
            var self = this;
            
            // Navigation buttons
            this.container.on('click', '.quiz-next', function(e) {
                e.preventDefault();
                self.nextQuestion();
            });
            
            this.container.on('click', '.quiz-prev', function(e) {
                e.preventDefault();
                self.prevQuestion();
            });
            
            // Form submission
            this.form.on('submit', function(e) {
                e.preventDefault();
                self.submitQuiz();
            });
            
            // Answer selection
            this.container.on('change', '.option-input', function() {
                var questionId = $(this).closest('.money-quiz-question').data('question');
                self.saveAnswer(questionId);
                
                // Auto-advance if enabled
                if (window.money_quiz_ajax && window.money_quiz_ajax.settings.auto_advance) {
                    setTimeout(function() {
                        self.nextQuestion();
                    }, 300);
                }
            });
            
            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (!self.container.is(':visible')) return;
                
                if (e.key === 'ArrowRight' || e.key === 'Enter') {
                    self.nextQuestion();
                } else if (e.key === 'ArrowLeft') {
                    self.prevQuestion();
                }
            });
        },
        
        // Show specific question
        showQuestion: function(index) {
            if (index < 0 || index >= this.totalQuestions) return;
            
            this.currentQuestion = index;
            
            // Hide all questions
            this.container.find('.money-quiz-question').removeClass('active');
            
            // Show current question
            this.container.find('.money-quiz-question').eq(index).addClass('active');
            
            // Update navigation
            this.updateNavigation();
            
            // Update progress
            this.updateProgress();
            
            // Scroll to top of quiz
            if (window.money_quiz_ajax && window.money_quiz_ajax.settings.auto_scroll) {
                $('html, body').animate({
                    scrollTop: this.container.offset().top - 100
                }, 300);
            }
        },
        
        // Next question
        nextQuestion: function() {
            // Validate current answer
            if (!this.validateCurrentQuestion()) {
                this.showError(window.money_quiz_ajax.messages.required);
                return;
            }
            
            if (this.currentQuestion < this.totalQuestions - 1) {
                this.showQuestion(this.currentQuestion + 1);
            }
        },
        
        // Previous question
        prevQuestion: function() {
            if (this.currentQuestion > 0) {
                this.showQuestion(this.currentQuestion - 1);
            }
        },
        
        // Validate current question
        validateCurrentQuestion: function() {
            var currentQ = this.container.find('.money-quiz-question').eq(this.currentQuestion);
            var checked = currentQ.find('.option-input:checked').length;
            return checked > 0;
        },
        
        // Save answer
        saveAnswer: function(questionNum) {
            var question = this.container.find('.money-quiz-question').eq(questionNum - 1);
            var questionId = question.find('.option-input').attr('name').match(/\d+/)[0];
            var answer = question.find('.option-input:checked').val();
            
            if (answer) {
                this.answers[questionId] = answer;
            }
            
            // Save progress if enabled
            if (window.money_quiz_ajax && window.money_quiz_ajax.settings.save_progress) {
                this.saveProgress();
            }
        },
        
        // Update navigation buttons
        updateNavigation: function() {
            var prevBtn = this.container.find('.quiz-prev');
            var nextBtn = this.container.find('.quiz-next');
            var submitBtn = this.container.find('.quiz-submit');
            
            // Previous button
            if (this.currentQuestion === 0) {
                prevBtn.hide();
            } else {
                prevBtn.show();
            }
            
            // Next/Submit buttons
            if (this.currentQuestion === this.totalQuestions - 1) {
                nextBtn.hide();
                submitBtn.show();
            } else {
                nextBtn.show();
                submitBtn.hide();
            }
        },
        
        // Update progress bar
        updateProgress: function() {
            var progress = ((this.currentQuestion + 1) / this.totalQuestions) * 100;
            
            this.container.find('.progress-fill').css('width', progress + '%');
            this.container.find('.current-question').text(this.currentQuestion + 1);
        },
        
        // Submit quiz
        submitQuiz: function() {
            var self = this;
            
            // Validate all answers
            if (!this.validateAllQuestions()) {
                this.showError(window.money_quiz_ajax.messages.required);
                return;
            }
            
            // Show loading
            this.showLoading();
            
            // Prepare data
            var formData = this.form.serializeArray();
            
            // Add answers
            $.each(this.answers, function(questionId, answer) {
                formData.push({
                    name: 'answers[' + questionId + ']',
                    value: answer
                });
            });
            
            // Submit via AJAX
            $.ajax({
                url: window.money_quiz_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        self.handleSuccess(response.data);
                    } else {
                        self.showError(response.data.message || window.money_quiz_ajax.messages.error);
                    }
                },
                error: function(xhr, status, error) {
                    self.hideLoading();
                    self.showError(window.money_quiz_ajax.messages.error);
                    
                    if (window.money_quiz_ajax.settings.debug) {
                        console.error('Money Quiz Error:', error);
                    }
                }
            });
        },
        
        // Validate all questions
        validateAllQuestions: function() {
            var valid = true;
            var self = this;
            
            this.container.find('.money-quiz-question').each(function(index) {
                var questionId = $(this).find('.option-input').attr('name').match(/\d+/)[0];
                if (!self.answers[questionId]) {
                    valid = false;
                    return false;
                }
            });
            
            return valid;
        },
        
        // Handle success response
        handleSuccess: function(data) {
            // Hide form
            this.form.hide();
            this.container.find('.money-quiz-progress').hide();
            
            // Show results
            if (data.html) {
                this.container.find('.money-quiz-results').html(data.html).show();
            } else {
                this.showMessage(data.message || window.money_quiz_ajax.messages.complete);
            }
            
            // Redirect if URL provided
            if (data.redirect_url) {
                setTimeout(function() {
                    window.location.href = data.redirect_url;
                }, 2000);
            }
            
            // Trigger custom event
            $(document).trigger('money-quiz-completed', [data]);
        },
        
        // Save progress
        saveProgress: function() {
            var data = {
                action: 'money_quiz_save_progress',
                nonce: window.money_quiz_ajax.nonce,
                quiz_id: this.form.find('input[name="quiz_id"]').val(),
                progress: this.answers
            };
            
            $.post(window.money_quiz_ajax.ajax_url, data);
        },
        
        // Show loading
        showLoading: function() {
            this.form.find('.quiz-submit').prop('disabled', true).text(window.money_quiz_ajax.messages.submitting);
            this.container.append('<div class="money-quiz-loading"></div>');
        },
        
        // Hide loading
        hideLoading: function() {
            this.form.find('.quiz-submit').prop('disabled', false).text('Get My Results');
            this.container.find('.money-quiz-loading').remove();
        },
        
        // Show error message
        showError: function(message) {
            var error = $('<div class="money-quiz-error">' + message + '</div>');
            this.container.prepend(error);
            
            setTimeout(function() {
                error.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        // Show success message
        showMessage: function(message) {
            var msg = $('<div class="money-quiz-success">' + message + '</div>');
            this.container.find('.money-quiz-results').html(msg).show();
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MoneyQuiz.init();
    });
    
    // Make available globally
    window.MoneyQuiz = MoneyQuiz;

})(jQuery);