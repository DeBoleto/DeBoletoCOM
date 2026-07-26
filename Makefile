.PHONY: start stop

start:
	npm run start

stop:
ifeq ($(OS),Windows_NT)
	taskkill /F /IM node.exe 2>nul || true
	taskkill /F /IM php.exe 2>nul || true
else
	pkill -f "vite" 2>/dev/null; pkill -f "artisan serve" 2>/dev/null; true
endif
