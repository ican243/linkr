/*
 * Linkr 공통 스크립트
 *
 * 복사 버튼: data-copy="복사할 글자"가 붙은 버튼을 누르면 그 글자를 클립보드에 복사하고,
 * 버튼 글자를 잠깐 "복사됨 ✓"으로 바꾼 뒤 화면 아래에 알림(토스트)을 띄운다.
 * 예) <button type="button" class="btn btn-sm btn-outline-primary king-copy" data-copy="https://...">복사</button>
 */
(function () {
    'use strict';

    // 클립보드 복사. 최신 방식(navigator.clipboard)은 https에서만 동작하므로,
    // http(커스텀 도메인 등)에서는 숨긴 입력칸을 만들어 선택 후 복사하는 예전 방식으로 대신함.
    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();

            var ok = false;
            try {
                ok = document.execCommand('copy');
            } catch (e) {
                ok = false;
            }

            document.body.removeChild(area);
            ok ? resolve() : reject(new Error('copy failed'));
        });
    }

    var toastEl = null;
    var toastTimer = null;

    function showToast(message) {
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.className = 'king-toast';
            toastEl.setAttribute('role', 'status');
            toastEl.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastEl);
        }

        toastEl.textContent = message;
        toastEl.classList.add('is-visible');

        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toastEl.classList.remove('is-visible');
        }, 1800);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-copy]');

        if (!button) {
            return;
        }

        var text = button.getAttribute('data-copy');

        copyText(text).then(function () {
            if (!button.dataset.label) {
                button.dataset.label = button.innerHTML;
            }

            button.innerHTML = '복사됨 ✓';
            button.classList.add('is-copied');
            showToast('클립보드에 복사했어요: ' + text);

            setTimeout(function () {
                button.innerHTML = button.dataset.label;
                button.classList.remove('is-copied');
            }, 1500);
        }).catch(function () {
            showToast('복사하지 못했어요. 주소를 길게 눌러 직접 복사해주세요.');
        });
    });
})();
