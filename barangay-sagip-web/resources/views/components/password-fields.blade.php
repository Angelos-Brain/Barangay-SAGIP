{{--
    New-password + confirm fields with a live strength hint. The hint is
    advisory only; the server enforces the actual rules (8+ characters, not
    the responder's mobile number or email).
--}}
<div class="space-y-5">
    <div>
        <x-auth-field label="Password" name="password" type="password" placeholder="At least 8 characters"
                      autocomplete="new-password" minlength="8" autofocus
                      data-password-strength />
        <div class="mt-2" id="password-strength" aria-live="polite">
            <div class="flex gap-1" aria-hidden="true">
                @foreach (range(1, 4) as $segment)
                    <span data-strength-bar class="h-1.5 flex-1 rounded-full bg-line transition-colors duration-200"></span>
                @endforeach
            </div>
            <p data-strength-label class="mt-1 text-xs text-muted-fg">Use at least 8 characters. Mixing letters, numbers, and symbols makes it stronger.</p>
        </div>
    </div>

    <x-auth-field label="Confirm Password" name="password_confirmation" type="password"
                  placeholder="Re-enter your password" autocomplete="new-password" />
</div>

@once
    @push('scripts')
    <script>
        (function () {
            const input = document.querySelector('[data-password-strength]');
            if (!input) return;

            const bars = document.querySelectorAll('[data-strength-bar]');
            const label = document.querySelector('[data-strength-label]');
            const levels = [
                { text: 'Use at least 8 characters. Mixing letters, numbers, and symbols makes it stronger.', color: 'bg-line' },
                { text: 'Weak — add more characters or mix in numbers and symbols.', color: 'bg-danger' },
                { text: 'Fair — a little longer or more varied would help.', color: 'bg-warning' },
                { text: 'Good password.', color: 'bg-accent' },
                { text: 'Strong password.', color: 'bg-success' },
            ];

            function score(value) {
                if (value.length === 0) return 0;
                if (value.length < 8) return 1;
                let points = 1;
                if (/[a-z]/.test(value) && /[A-Z]/.test(value)) points++;
                if (/\d/.test(value)) points++;
                if (/[^A-Za-z0-9]/.test(value)) points++;
                if (value.length >= 12) points++;
                return Math.min(4, points);
            }

            input.addEventListener('input', function () {
                const level = levels[score(input.value)];
                bars.forEach(function (bar, index) {
                    bar.classList.remove('bg-line', 'bg-danger', 'bg-warning', 'bg-accent', 'bg-success');
                    bar.classList.add(index < score(input.value) ? level.color : 'bg-line');
                });
                label.textContent = level.text;
            });
        })();
    </script>
    @endpush
@endonce
