all:
	if [[ -e bitrix-devtm-erip.zip ]]; then rm bitrix-devtm-erip.zip; fi
	if [[ -e bitrix-devtm-erip-windows-1251.zip ]]; then rm bitrix-devtm-erip-windows-1251.zip; fi
	 zip -r bitrix-devtm-erip.zip devtm.erip
	 sed -i '' 's/utf-8/windows-1251/g' devtm.erip/install/index.php
	 find devtm.erip -name \*.php -exec sh -c 'iconv -f utf-8 -t cp1251 {} > {}.1251 && mv {}.1251 {}' \;
	 zip -r bitrix-devtm-erip-windows-1251.zip devtm.erip
	 git checkout -f devtm.erip
