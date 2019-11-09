(function ($) {
  var lastEmail = ''
  var isValid = false

  /**
   * Validate and check for disposable/temporary/throw away emails using validator.pizza
   *
   * @param {object} options
   * @param {function(string):boolean} options.validateEmail - Optional custom function to validate the email regex.
   * @param {function(boolean):void}   options.validFeedback - A callback to run whether or not the email is valid.
   * @param {function(string):void}    options.didYouMean    - A callback to run if an email suggestion is available.
   */
  $.fn.emailValidator = function (options) {
    var email = this.val()

    if (lastEmail === email) return

    lastEmail = email

    var functions = $.extend({
      validateEmail: function (email) {
        if ($.trim(email).length === 0) return false

        var filter = /^[A-Z0-9._%+-]+@([A-Z0-9-]+\.)+[A-Z]{2,4}$/i

        if (!filter.test(email)) return false

        return true
      },
      validFeedback: function (isValid) {},
      didYouMean: function (suggestion) {}
    }, options)

    isValid = functions.validateEmail(email)
    functions.validFeedback(isValid)

    if (isValid && email.length > 3) {
      $.ajax({
        url: 'https://www.validator.pizza/email/' + email,
        type: 'GET',
        dataType: 'json',
        success: function (data) {
          if (data.status === 400 || data.disposable) isValid = functions.validFeedback(false)

          if (data.did_you_mean) data.did_you_mean = email.split('@')[0] + '@' + data.did_you_mean

          functions.didYouMean(data.did_you_mean)
        }
      })
    }
    return this
  }
}(jQuery))
