{{--
    Browser-side half of the Gmail-only rule (GmailAddress enforces it on the
    server). Any input marked `data-gmail-only` is checked on submit; a
    non-Gmail address stops the submit and shows the same message the server
    would return.
--}}
@once
@push('scripts')
<script>
    (function () {
        const message = @js(\App\Rules\GmailAddress::MESSAGE);

        function isGmail(value) {
            const match = value.trim().toLowerCase().match(/^([^@\s]+)@gmail\.com$/);
            return match !== null && match[1].split('+')[0].replace(/\./g, '') !== '';
        }

        function errorFor(input) {
            let error = document.getElementById(input.id + '-gmail-error');
            if (!error) {
                error = document.createElement('p');
                error.id = input.id + '-gmail-error';
                error.className = 'mt-1.5 text-sm text-danger';
                error.setAttribute('role', 'alert');
                input.insertAdjacentElement('afterend', error);
            }
            return error;
        }

        document.querySelectorAll('input[data-gmail-only]').forEach(function (input) {
            input.addEventListener('input', function () {
                input.setCustomValidity('');
                input.removeAttribute('aria-invalid');
                const error = document.getElementById(input.id + '-gmail-error');
                if (error) error.remove();
            });

            input.form.addEventListener('submit', function (event) {
                if (input.value.trim() === '' || isGmail(input.value)) return;
                event.preventDefault();
                input.setAttribute('aria-invalid', 'true');
                errorFor(input).textContent = message;
                input.focus();
            });
        });
    })();
</script>
@endpush
@endonce
