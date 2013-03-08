/**
 * WeMentions' plugins javascript file
 * 
 * @package Dragooon:WeMentions
 * @author Shitiz "Dragooon" Garg <Email mail@dragooon.net> <Url http://smf-media.com>
 * @copyright 2013, Shitiz "Dragooon" Garg <mail@dragooon.net>
 * @license
 *      Licensed under "New BSD License (3-clause version)"
 *      http://www.opensource.org/licenses/BSD-3-Clause
 * @version 1.0
 */

$(function()
{
   $.fn.getCursorPosition = function()
   {
        var el = $(this).get(0);
        var pos = 0;
        if('selectionStart' in el)
        {
            pos = el.selectionStart;
        }
        else if('selection' in document)
        {
            el.focus();
            var Sel = document.selection.createRange();
            var SelLength = document.selection.createRange().text.length;
            Sel.moveStart('character', -el.value.length);
            pos = Sel.text.length - SelLength;
        }
        return pos;
    };
    $.fn.selectRange = function(start, end)
    {
        return this.each(function()
        {
            if (this.setSelectionRange)
            {
                this.focus();
                this.setSelectionRange(start, end);
            }
            else if (this.createTextRange)
            {
                var range = this.createTextRange();
                range.collapse(true);
                range.moveEnd('character', end);
                range.moveStart('character', start);
                range.select();
            }
        });
    };

    var $editor = $('#message'),
        mentioning = false,
        memberName = '',
        start = -1,
        $container = null,
        keyCodeFired = false;

    $editor.on('keydown', function(e)
    {
        // Keyboard navigation for container
        if (mentioning && memberName.length >= 3)
        {
            var keyCode = e.keyCode;

            // Moving down!
            if (keyCode == 40)
            {
                if ($container.find('.auto_suggest_hover').length > 0)
                    $container.find('.auto_suggest_hover').mouseleave().next().mouseenter();
                else
                    $container.find('div:first').mouseenter();
            }
            // Moving up!
            else if (keyCode == 38)
            {
                if ($container.find('.auto_suggest_hover').length > 0)
                    $container.find('.auto_suggest_hover').mouseleave().prev().mouseenter();
                else
                    $container.find('div:first').mouseenter();
            }
            // Selecting!
            else if (keyCode == 13)
                $container.find('.auto_suggest_hover').click();

            if (keyCode == 40 || keyCode == 38 || keyCode == 13)
            {
                // Kind of an ugly hack, couldn't get the next even from firing
                keyCodeFired = true;
                return false;
            }
        }
    });

    $editor.on('keyup', function(e)
    {
        if (keyCodeFired)
        {
            keyCodeFired = false;
            return true;
        }

        var pos = $(this).getCursorPosition() - 1;
        var val = $(this).val();
        
        if (!mentioning && val.charAt(pos) == '@')
        {
            mentioning = true;
            start = pos + 1;
        }
        else if (mentioning && (/\s+/.test(val.charAt(pos)) || val.charAt(start - 1) != '@'))
        {
            mentioning = false;
            if ($container != null)
                $container.remove();
        }
        else if (mentioning)
        {
            memberName = val.substr(start, pos);

            if (memberName.length < 3)
                return true;

            if ($container != null)
                $container.remove();

            $container = $('<div></div>').addClass('auto_suggest');
            $.ajax({
                url: we_script + '?action=suggest&' + we_sessvar + '=' + we_sessid,
                method: 'GET',
                data: {
                    search: memberName,
                    type: 'member',
                },
                success: function(data)
                {
                    var members = $(data).find('we > items > item');
                    $.each(members, function(index, item)
                    {
                        $container
                            .append(
                                $('<div></div>')
                                    .text($(item).text())
                                    .attr('data-id', $(item).attr('id'))
                                    .hover(function()
                                    {
                                        $(this).addClass('auto_suggest_hover');
                                    }, function()
                                    {
                                        $(this).removeClass('auto_suggest_hover');
                                    })
                                    .click(function()
                                    {
                                        $editor.val(val.substr(0, start) + $(this).text() + ' ' + val.substr(pos + 1));

                                        var caretPos = start + $(this).text().length + 1;
                                        $editor.selectRange(caretPos, caretPos);

                                        $container.remove();
                                    })
                            );
                    });

                    var position = $editor.offset();
                    position.top += $editor.height();
                    $container.appendTo(document.body).offset(position);
                }
            });
        }
    })
})