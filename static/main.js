(function ($) {

    $(document).on('click', '#aiga-evaluate', function (e) {
        e.preventDefault();
        let $self = $(this);
        if ($self.hasClass('disabled')) {
            return false;
        }
        let id = $self.data('id');
        let defaultText = $self.text();
        let loadingText = $self.data('ltext');
        let url = AIGA_Main.eval_url;
        $.ajax({
            url: url,
            type: 'POST',
            cache: false,
            data: {essay_id: id},
            beforeSend: function () {
                $self.prop('disabled', true);
                $self.addClass('disabled');
                $self.text(loadingText);
            },
            success: function (response) {
                if (response.success && response.data.hasOwnProperty('resultHtml')) {
                    $('#aiga-grading-outcome').html(response.data.resultHtml);
                    if (response.data.points !== null) {
                        $(document).find('input[name=points_awarded]').val(response.data.points);
                        $(document).find('select[name=post_status]').val('graded');
                    }
                } else {
                    alert(response.data.message);
                }
            },
            complete: function () {
                $self.prop('disabled', false);
                $self.removeClass('disabled');
                $self.text(defaultText);
            }
        });
    });


    $(document).on('click', '#aiga-mark-as-solved', function (e) {
        e.preventDefault();
        let $self = $(this);
        if ($self.hasClass('disabled')) {
            return false;
        }
        let id = $self.data('id');
        let defaultText = $self.text();
        let loadingText = $self.data('ltext');
        let url = AIGA_Main.mark_as_solved_url;
        $.ajax({
            url: url,
            type: 'POST',
            cache: false,
            data: {essay_id: id},
            beforeSend: function () {
                $self.prop('disabled', true);
                $self.addClass('disabled');
                $self.text(loadingText);
            },
            success: function (response) {
                if (response.success && response.data.hasOwnProperty('resultHtml')) {
                    $('#aiga-grading-outcome').html(response.data.resultHtml);
                } else {
                    alert(response.data.message);
                }
            },
            complete: function () {
                $self.prop('disabled', false);
                $self.removeClass('disabled');
                $self.text(defaultText);
            }
        });
    });

    $(document).on('click', '.aiga-attention-needed--pagination a.button', function(e){
        e.preventDefault();
        let $self = $(this);
        if ($self.hasClass('disabled')) {
            return false;
        }
        let url = $self.attr('href');

        e.preventDefault();
        $.ajax({
            type: 'GET',
            url: url,
            beforeSend: function () {
                $self.prop('disabled', true);
                $self.addClass('disabled');
            },
            success: function (response) {
                let $contents = $(response);
                let $tbody = $contents.find('.aiga-attention-needed--table tbody');
                let $pagi  = $contents.find('.aiga-attention-needed--pagination');
                $self.closest('.aiga-attention-needed').find('.aiga-attention-needed--table tbody').append($tbody.html());
                $self.closest('.aiga-attention-needed').find('.aiga-attention-needed--pagination').html($pagi.html() ? $pagi.html() : '');
            },
            complete: function () {
                $self.prop('disabled', false);
                $self.removeClass('disabled');
            }

        });
    } );

    $(document).on('click', '.aiga-dismiss-attention-needed', function(e){
        e.preventDefault();
        let $self = $(this);
        let $wrap =  $self.closest('.aiga-attention-needed-wrap');
        let id = $self.data('id');

        $.ajax({
            type: 'POST',
            url: AIGA_Main.attention_dismiss_url,
            data: {id: id},
            success: function (response) {
                if(response.success) {
                    let $newHtml = $(response.data.resultHtml);
                    let $statusBox = $newHtml.find('.aiga-attention-needed--status');
                    $self.closest('tr').remove().detach();
                    $wrap.find('.aiga-attention-needed')
                        .removeClass('aiga-attention-needed--nok')
                        .removeClass('aiga-attention-needed--nok')
                        .attr('class', $newHtml.find('.aiga-attention-needed').attr('class'));
                    $wrap.find('.aiga-attention-needed--status').html($statusBox.html());

                    if(($wrap.find('.aiga-attention-needed--table tbody tr')).length === 0) {
                        $wrap.find('.aiga-attention-needed--table').remove().detach();
                    }
                }
            },
        });
    })

})(jQuery);