<!DOCTYPE html>
<html>

<body style="font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <p>A new entry was submitted to <strong>{{ $formName }}</strong> on {{ $submittedAt }}.</p>

    @if ($fields === [])
        <p>This form has no fields.</p>
    @else
        <table cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
            @foreach ($fields as $field)
                <tr>
                    <th align="left" valign="top" style="border-bottom: 1px solid #e5e7eb;">{{ $field['label'] }}</th>
                    <td valign="top" style="border-bottom: 1px solid #e5e7eb; white-space: pre-wrap;">
                        {{ $field['value'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>

</html>
