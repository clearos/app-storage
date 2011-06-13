
Name: app-storage-core
Group: ClearOS/Libraries
Version: 5.9.9.2
Release: 2.2%{dist}
Summary: Storage summary - APIs and install
License: LGPLv3
Packager: ClearFoundation
Vendor: ClearFoundation
Source: app-storage-%{version}.tar.gz
Buildarch: noarch
Requires: app-base-core
Requires: initscripts

%description
Storage long description

This package provides the core API and libraries.

%prep
%setup -q -n app-storage-%{version}
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/storage
cp -r * %{buildroot}/usr/clearos/apps/storage/

install -d -m 0755 %{buildroot}/etc/storage.d
install -d -m 0755 %{buildroot}/store
install -d -m 0755 %{buildroot}/var/clearos/storage
install -d -m 0755 %{buildroot}/var/clearos/storage/plugins
install -D -m 0644 packaging/home-default.conf %{buildroot}/etc/storage.d/home-default.conf
install -D -m 0644 packaging/home.php %{buildroot}/var/clearos/storage/plugins/home.php
install -D -m 0644 packaging/storage.conf %{buildroot}/etc/storage.conf
install -D -m 0755 packaging/storage.init %{buildroot}/etc/rc.d/init.d/storage

%post
logger -p local6.notice -t installer 'app-storage-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/storage/deploy/install ] && /usr/clearos/apps/storage/deploy/install
fi

[ -x /usr/clearos/apps/storage/deploy/upgrade ] && /usr/clearos/apps/storage/deploy/upgrade

exit 0

%preun
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-storage-core - uninstalling'
    [ -x /usr/clearos/apps/storage/deploy/uninstall ] && /usr/clearos/apps/storage/deploy/uninstall
fi

exit 0

%files
%defattr(-,root,root)
%exclude /usr/clearos/apps/storage/packaging
%exclude /usr/clearos/apps/storage/tests
%dir /usr/clearos/apps/storage
%dir /etc/storage.d
%dir /store
%dir /var/clearos/storage
%dir /var/clearos/storage/plugins
/usr/clearos/apps/storage/deploy
/usr/clearos/apps/storage/language
/usr/clearos/apps/storage/libraries
/etc/storage.d/home-default.conf
/var/clearos/storage/plugins/home.php
/etc/storage.conf
/etc/rc.d/init.d/storage
