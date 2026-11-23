/* 旅行信息分享平台前端交互：路线动态行、图片回退、聊天滚动。 */
(function () {
    'use strict';

    // 路线编辑：新增一行
    const addRowButton = document.getElementById('add-route-row');
    const rowContainer = document.getElementById('route-rows');
    const rowTemplate = document.getElementById('route-row-template');
    if (addRowButton && rowContainer && rowTemplate) {
        addRowButton.addEventListener('click', function () {
            const rows = rowContainer.querySelectorAll('.route-edit-row');
            const last = rows.length ? rows[rows.length - 1] : null;
            const fragment = rowTemplate.content.cloneNode(true);
            if (last) {
                const dayInput = fragment.querySelector('[name="day_no[]"]');
                const seqInput = fragment.querySelector('[name="seq[]"]');
                const lastDay = last.querySelector('[name="day_no[]"]');
                const lastSeq = last.querySelector('[name="seq[]"]');
                if (dayInput && lastDay) {
                    dayInput.value = lastDay.value || '1';
                }
                if (seqInput && lastSeq) {
                    seqInput.value = String((parseInt(lastSeq.value, 10) || 0) + 1);
                }
            }
            rowContainer.appendChild(fragment);
        });
    }

    // 路线编辑：删除一行（保留至少一行）
    document.addEventListener('click', function (event) {
        const removeButton = event.target.closest('[data-remove-route-row]');
        if (!removeButton || !rowContainer) {
            return;
        }
        const rows = rowContainer.querySelectorAll('.route-edit-row');
        if (rows.length <= 1) {
            return;
        }
        const row = removeButton.closest('.route-edit-row');
        if (row) {
            row.remove();
        }
    });

    // 图片加载失败时回退到本地占位图
    document.querySelectorAll('img').forEach(function (image) {
        image.addEventListener('error', function () {
            if (image.dataset.fallback === '1') {
                return;
            }
            image.dataset.fallback = '1';
            image.src = image.classList.contains('avatar') ? '/assets/avatar.svg' : '/assets/cover.svg';
        });
    });

    // 私聊窗口滚动到底部
    const thread = document.getElementById('message-thread');
    if (thread) {
        thread.scrollTop = thread.scrollHeight;
    }

    // 上传页根据资源类型调整文件选择提示
    const typeSelect = document.getElementById('type');
    const fileInput = document.getElementById('file');
    if (typeSelect && fileInput) {
        const accepts = {
            photo: 'image/jpeg,image/png,image/gif,image/webp',
            video: 'video/mp4,video/webm',
            doc: 'application/pdf'
        };
        const syncAccept = function () {
            fileInput.setAttribute('accept', accepts[typeSelect.value] || '');
        };
        typeSelect.addEventListener('change', syncAccept);
        syncAccept();
    }
})();
