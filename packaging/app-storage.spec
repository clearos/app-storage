
Name: app-storage
Epoch: 1
Version: 2.5.0
Release: 1%{dist}
Summary: Storage Manager
License: GPLv3
Group: Applications/Apps
Packager: ClearFoundation
Vendor: ClearFoundation
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = 1:%{version}-%{release}
Requires: app-base
Requires: app-base

%description
The Storage Manager allows you to map large data shares to storage volumes.

%package core
Summary: Storage Manager - API
License: LGPLv3
Group: Applications/API
Requires: app-base-core
Requires: app-base-core >= 1:1.4.31
Requires: app-events-core
Requires: csplugin-filewatch
Requires: initscripts
Requires: parted
Requires: util-linux-ng

%description core
The Storage Manager allows you to map large data shares to storage volumes.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/storage
cp -r * %{buildroot}/usr/clearos/apps/storage/

install -d -m 0755 %{buildroot}/etc/clearos/storage.d
install -d -m 0755 %{buildroot}/store
install -d -m 0755 %{buildroot}/var/clearos/events/storage
install -d -m 0755 %{buildroot}/var/clearos/storage
install -d -m 0775 %{buildroot}/var/clearos/storage/lock
install -d -m 0755 %{buildroot}/var/clearos/storage/plugins
install -d -m 0755 %{buildroot}/var/clearos/storage/state
install -D -m 0755 packaging/app-storage-create %{buildroot}/usr/sbin/app-storage-create
install -D -m 0644 packaging/filewatch-storage-event.conf %{buildroot}/etc/clearsync.d/filewatch-storage-event.conf
install -D -m 0755 packaging/storage %{buildroot}/usr/sbin/storage
install -D -m 0644 packaging/storage.conf %{buildroot}/etc/clearos/storage.conf
install -D -m 0755 packaging/storage.init %{buildroot}/etc/rc.d/init.d/storage
install -D -m 0755 packaging/system-database-event %{buildroot}/var/clearos/events/storage/system-database

%post
logger -p local6.notice -t installer 'app-storage - installing'

%post core
logger -p local6.notice -t installer 'app-storage-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/storage/deploy/install ] && /usr/clearos/apps/storage/deploy/install
fi

[ -x /usr/clearos/apps/storage/deploy/upgrade ] && /usr/clearos/apps/storage/deploy/upgrade

exit 0

%preun
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-storage - uninstalling'
fi

%preun core
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-storage-core - uninstalling'
    [ -x /usr/clearos/apps/storage/deploy/uninstall ] && /usr/clearos/apps/storage/deploy/uninstall
fi

exit 0

%files
%defattr(-,root,root)
/usr/clearos/apps/storage/controllers
/usr/clearos/apps/storage/htdocs
/usr/clearos/apps/storage/views

%files core
%defattr(-,root,root)
%exclude /usr/clearos/apps/storage/packaging
%exclude /usr/clearos/apps/storage/unify.json
%dir /usr/clearos/apps/storage
%dir /etc/clearos/storage.d
%dir /store
%dir /var/clearos/events/storage
%dir /var/clearos/storage
%dir %attr(0775,root,webconfig) /var/clearos/storage/lock
%dir /var/clearos/storage/plugins
%dir /var/clearos/storage/state
/usr/clearos/apps/storage/deploy
/usr/clearos/apps/storage/language
/usr/clearos/apps/storage/libraries
/usr/sbin/app-storage-create
/etc/clearsync.d/filewatch-storage-event.conf
/usr/sbin/storage
%config(noreplace) /etc/clearos/storage.conf
/etc/rc.d/init.d/storage
/var/clearos/events/storage/system-database
