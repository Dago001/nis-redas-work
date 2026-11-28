@once
<script>
(function () {
    if (window.RedasDraftFiles) return;

    var DB_NAME = 'redas_draft_files_v1';
    var STORE_NAME = 'draft_files';

    function openDb() {
        return new Promise(function (resolve) {
            if (!window.indexedDB) {
                resolve(null);
                return;
            }
            var req = window.indexedDB.open(DB_NAME, 1);
            req.onupgradeneeded = function (e) {
                var db = e.target.result;
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    db.createObjectStore(STORE_NAME, { keyPath: 'key' });
                }
            };
            req.onsuccess = function (e) { resolve(e.target.result); };
            req.onerror = function () { resolve(null); };
        });
    }

    function fileToRecord(file) {
        return file.arrayBuffer().then(function (buffer) {
            return {
                name: file.name,
                type: file.type || 'application/octet-stream',
                lastModified: file.lastModified || Date.now(),
                buffer: buffer
            };
        });
    }

    function recordToFile(rec) {
        return new File([rec.buffer], rec.name, {
            type: rec.type || 'application/octet-stream',
            lastModified: rec.lastModified || Date.now()
        });
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderInlineFileList(input) {
        if (!input || input.id === 'attachInput') return;
        var host = input.parentElement;
        if (!host) return;
        var list = host.querySelector('.redas-restored-files');
        var files = Array.from(input.files || []);
        if (!files.length) {
            if (list) list.remove();
            return;
        }
        if (!list) {
            list = document.createElement('div');
            list.className = 'redas-restored-files';
            list.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;';
            input.insertAdjacentElement('afterend', list);
        }
        list.innerHTML = '';
        files.forEach(function (f, idx) {
            var chip = document.createElement('span');
            chip.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;font-size:.75rem;font-weight:600;';
            var sizeKb = Math.max(1, Math.round((f.size || 0) / 1024));
            chip.innerHTML = '<i class="fas fa-paperclip" style="font-size:.7rem;"></i><span>' + escHtml(f.name) + '</span><span style="color:#047857;font-weight:400;">(' + sizeKb + 'KB)</span><button type="button" style="background:none;border:none;color:#065f46;cursor:pointer;padding:0 2px;line-height:1;" title="Remove file">&times;</button>';
            chip.querySelector('button').addEventListener('click', function () {
                var current = Array.from(input.files || []);
                current.splice(idx, 1);
                try {
                    var dt = new DataTransfer();
                    current.forEach(function (item) { dt.items.add(item); });
                    input.files = dt.files;
                } catch (e) {}
                renderInlineFileList(input);
            });
            list.appendChild(chip);
        });
    }

    function findTargetInput(form, savedInput) {
        if (savedInput.id) {
            var byId = form.querySelector('#' + CSS.escape(savedInput.id));
            if (byId) return byId;
        }
        if (savedInput.name) {
            var selector = 'input[type="file"][name="' + CSS.escape(savedInput.name) + '"]';
            var wantedIdx = typeof savedInput.nameIndex === 'number' ? savedInput.nameIndex : 0;
            var matches = Array.from(form.querySelectorAll(selector));
            if (matches.length <= wantedIdx) {
                var addBtn = form.querySelector('#passportAddDocumentRow, [id*="AddDocument"], .add-document-row');
                var guard = 0;
                while (addBtn && matches.length <= wantedIdx && guard++ < 30) {
                    var before = matches.length;
                    addBtn.click();
                    matches = Array.from(form.querySelectorAll(selector));
                    if (matches.length === before) break;
                }
            }
            if (matches[wantedIdx]) return matches[wantedIdx];
        }
        if (typeof savedInput.index === 'number') {
            var all = Array.from(form.querySelectorAll('input[type="file"]'));
            if (all[savedInput.index]) return all[savedInput.index];
        }
        return null;
    }

    window.RedasDraftFiles = {
        save: function (draftKey, form) {
            if (!draftKey || !form) return Promise.resolve(0);
            var fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
            var nameCounts = {};
            var inputPromises = fileInputs.map(function (input, idx) {
                var keyName = input.name || '';
                var nameIndex = nameCounts[keyName] || 0;
                nameCounts[keyName] = nameIndex + 1;

                var files = Array.from(input.files || []);
                if (!files.length) return Promise.resolve(null);
                return Promise.all(files.map(fileToRecord)).then(function (serialized) {
                    return {
                        id: input.id || '',
                        name: keyName,
                        nameIndex: nameIndex,
                        index: idx,
                        files: serialized
                    };
                });
            });

            return Promise.all(inputPromises).then(function (entries) {
                var nonEmpty = entries.filter(Boolean);
                var totalFiles = nonEmpty.reduce(function (sum, item) { return sum + item.files.length; }, 0);
                return openDb().then(function (db) {
                    if (!db) return totalFiles;
                    return new Promise(function (resolve) {
                        try {
                            var tx = db.transaction(STORE_NAME, 'readwrite');
                            var store = tx.objectStore(STORE_NAME);
                            if (nonEmpty.length) {
                                store.put({ key: draftKey, updatedAt: Date.now(), inputs: nonEmpty });
                            } else {
                                store.delete(draftKey);
                            }
                            tx.oncomplete = function () { resolve(totalFiles); };
                            tx.onerror = function () { resolve(totalFiles); };
                        } catch (e) {
                            resolve(totalFiles);
                        }
                    });
                });
            }).catch(function () { return 0; });
        },

        restore: function (draftKey, form) {
            if (!draftKey || !form) return Promise.resolve(0);
            return openDb().then(function (db) {
                if (!db) return 0;
                return new Promise(function (resolve) {
                    try {
                        var tx = db.transaction(STORE_NAME, 'readonly');
                        var req = tx.objectStore(STORE_NAME).get(draftKey);
                        req.onsuccess = function () {
                            var record = req.result;
                            if (!record || !Array.isArray(record.inputs) || !record.inputs.length) {
                                resolve(0);
                                return;
                            }
                            var restoredCount = 0;
                            record.inputs.forEach(function (savedInput) {
                                var target = findTargetInput(form, savedInput);
                                if (!target || !Array.isArray(savedInput.files) || !savedInput.files.length) return;

                                try {
                                    var dt = new DataTransfer();
                                    var restoredFiles = [];
                                    savedInput.files.forEach(function (rec) {
                                        var f = recordToFile(rec);
                                        dt.items.add(f);
                                        restoredFiles.push(f);
                                    });
                                    target.files = dt.files;
                                    restoredCount += restoredFiles.length;
                                    target.dispatchEvent(new CustomEvent('redas:files-restored', {
                                        bubbles: true,
                                        detail: { files: restoredFiles }
                                    }));
                                    renderInlineFileList(target);
                                    if (!target.dataset.redasInlineBound) {
                                        target.dataset.redasInlineBound = '1';
                                        target.addEventListener('change', function () {
                                            renderInlineFileList(target);
                                        });
                                    }
                                } catch (e) {}
                            });
                            resolve(restoredCount);
                        };
                        req.onerror = function () { resolve(0); };
                    } catch (e) {
                        resolve(0);
                    }
                });
            }).catch(function () { return 0; });
        },

        clear: function (draftKey) {
            if (!draftKey) return Promise.resolve();
            return openDb().then(function (db) {
                if (!db) return;
                return new Promise(function (resolve) {
                    try {
                        var tx = db.transaction(STORE_NAME, 'readwrite');
                        tx.objectStore(STORE_NAME).delete(draftKey);
                        tx.oncomplete = function () { resolve(); };
                        tx.onerror = function () { resolve(); };
                    } catch (e) { resolve(); }
                });
            }).catch(function () {});
        },

        clearPrefix: function (prefix) {
            if (!prefix) return Promise.resolve();
            return openDb().then(function (db) {
                if (!db) return;
                return new Promise(function (resolve) {
                    try {
                        var tx = db.transaction(STORE_NAME, 'readwrite');
                        var store = tx.objectStore(STORE_NAME);
                        var cursorReq = store.openCursor();
                        cursorReq.onsuccess = function (e) {
                            var cursor = e.target.result;
                            if (!cursor) return;
                            if (typeof cursor.key === 'string' && cursor.key.indexOf(prefix) === 0) {
                                cursor.delete();
                            }
                            cursor.continue();
                        };
                        tx.oncomplete = function () { resolve(); };
                        tx.onerror = function () { resolve(); };
                    } catch (e) { resolve(); }
                });
            }).catch(function () {});
        }
    };
})();
</script>
@endonce
