Route::get('adlogin', [WebChatBridgeNewController::class, 'index'])->name('adlogin.index');
    Route::get('web-chat-new', [WebChatBridgeNewController::class, 'index'])->name('web-chat-new.index');
    Route::get('web-chat-new/conversations', [WebChatBridgeNewController::class, 'conversations'])->name('web-chat-new.conversations');
    Route::get('web-chat-new/history', [WebChatBridgeNewController::class, 'history'])->name('web-chat-new.history');
    Route::get('web-chat-new/stream', [WebChatBridgeNewController::class, 'stream'])->name('web-chat-new.stream');
    Route::post('web-chat-new/reply', [WebChatBridgeNewController::class, 'reply'])->name('web-chat-new.reply');
