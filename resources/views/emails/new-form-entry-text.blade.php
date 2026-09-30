A new entry was submitted to {{ $formName }} on {{ $submittedAt }}.

@forelse ($fields as $field)
{{ $field['label'] }}: {{ $field['value'] }}
@empty
This form has no fields.
@endforelse
