
Name: app-storage
Version: 6.2.0.beta3
Release: 1%{dist}
Summary: Storage Manager - APIs and install
License: LGPLv3
Group: ClearOS/Libraries
Source: app-storage-%{version}.tar.gz
Buildarch: noarch

%description
Storage long description

%package core
Summary: Storage Manager - APIs and install
Requires: app-base-core
Requires: initscripts

%description core
Storage long description

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/storage
cp -r * %{buildroot}/usr/clearos/apps/storage/

install -d -m 0755 %{buildroot}/etc/clearos/storage.d
install -d -m 0755 %{buildroot}/store
install -d -m 0755 %{buildroot}/var/clearos/storage
install -d -m 0755 %{buildroot}/var/clearos/storage/plugins
install -D -m 0755 packaging/storage %{buildroot}/usr/sbin/storage
install -D -m 0644 packaging/storage.conf %{buildroot}/etc/clearos/storage.conf
install -D -m 0755 packaging/storage.init %{buildroot}/etc/rc.d/init.d/storage

%post core
logger -p local6.notice -t installer 'app-storage-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/storage/deploy/install ] && /usr/clearos/apps/storage/deploy/install
fi

[ -x /usr/clearos/apps/storage/deploy/upgrade ] && /usr/clearos/apps/storage/deploy/upgrade

exit 0

%preun core
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-storage-core - uninstalling'
    [ -x /usr/clearos/apps/storage/deploy/uninstall ] && /usr/clearos/apps/storage/deploy/uninstall
fi

exit 0

%files core
%defattr(-,root,root)
%exclude /usr/clearos/apps/storage/packaging
%exclude /usr/clearos/apps/storage/tests
%dir /usr/clearos/apps/storage
%dir /etc/clearos/storage.d
%dir /store
%dir /var/clearos/storage
%dir /var/clearos/storage/plugins
/usr/clearos/apps/storage/deploy
/usr/clearos/apps/storage/language
/usr/clearos/apps/storage/libraries
/usr/sbin/storage
/etc/clearos/storage.conf
/etc/rc.d/init.d/storage
